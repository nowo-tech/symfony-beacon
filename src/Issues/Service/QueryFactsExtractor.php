<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Issues\Dto\QueryFacts;

/**
 * Derives Query facts from an Envelope event payload for issue/event UI.
 */
final class QueryFactsExtractor
{
    public const int MAX_SQL_DISPLAY = 8192;

    /** @var list<string> */
    private const array QUERY_BREADCRUMB_CATEGORIES = ['query', 'db', 'db.query', 'sql.query'];

    /**
     * @param array<string, mixed> $payload
     */
    public function extract(array $payload): ?QueryFacts
    {
        $fromMessage = $this->fromExceptionChain($payload);
        $fromBreadcrumb = $this->fromBreadcrumbs($payload);
        $fromStructured = $this->fromStructured($payload);

        $merged = $this->merge($this->merge($fromMessage, $fromBreadcrumb), $fromStructured);
        if (null === $merged || $merged->isEmpty()) {
            return null;
        }

        return $this->withSummary($merged, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fromStructured(array $payload): ?QueryFacts
    {
        $contexts = $payload['contexts'] ?? null;
        $db = \is_array($contexts) ? ($contexts['db'] ?? null) : null;
        if (\is_array($db)) {
            $facts = $this->fromMap($db, QueryFacts::SOURCE_STRUCTURED);
            if (null !== $facts) {
                return $facts;
            }
        }

        $extra = $payload['extra'] ?? null;
        if (!\is_array($extra)) {
            return null;
        }

        $map = [];
        if (isset($extra['sql']) && \is_string($extra['sql'])) {
            $map['sql'] = $extra['sql'];
        }
        if (isset($extra['query']) && \is_string($extra['query'])) {
            $map['sql'] = $extra['query'];
        }
        if (isset($extra['bindings'])) {
            $map['bindings'] = $extra['bindings'];
        }
        if (isset($extra['sqlstate']) && \is_string($extra['sqlstate'])) {
            $map['sqlstate'] = $extra['sqlstate'];
        }

        return $this->fromMap($map, QueryFacts::SOURCE_STRUCTURED);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fromBreadcrumbs(array $payload): ?QueryFacts
    {
        $breadcrumbs = $payload['breadcrumbs'] ?? null;
        if (!\is_array($breadcrumbs)) {
            return null;
        }
        $values = $breadcrumbs['values'] ?? $breadcrumbs;
        if (!\is_array($values)) {
            return null;
        }

        $last = null;
        foreach ($values as $crumb) {
            if (!\is_array($crumb)) {
                continue;
            }
            $category = strtolower((string) ($crumb['category'] ?? ''));
            if (!\in_array($category, self::QUERY_BREADCRUMB_CATEGORIES, true)) {
                continue;
            }
            $parsed = $this->fromBreadcrumb($crumb);
            if (null !== $parsed) {
                $last = $parsed;
            }
        }

        return $last;
    }

    /**
     * @param array<string, mixed> $crumb
     */
    private function fromBreadcrumb(array $crumb): ?QueryFacts
    {
        $data = $crumb['data'] ?? [];
        $sql = null;
        if (\is_array($data)) {
            foreach (['sql', 'query'] as $key) {
                if (isset($data[$key]) && \is_string($data[$key]) && '' !== $data[$key]) {
                    $sql = $data[$key];
                    break;
                }
            }
        }
        if (null === $sql && isset($crumb['message']) && \is_string($crumb['message']) && $this->looksLikeSql($crumb['message'])) {
            $sql = $crumb['message'];
        }
        if (null === $sql) {
            return null;
        }

        $bindings = \is_array($data) ? ($data['bindings'] ?? null) : null;

        return $this->finishSql(new QueryFacts(
            sql: $sql,
            bindings: \is_array($bindings) ? $bindings : null,
            source: QueryFacts::SOURCE_BREADCRUMB,
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fromExceptionChain(array $payload): ?QueryFacts
    {
        $merged = null;
        $values = $payload['exception']['values'] ?? null;
        if (\is_array($values)) {
            foreach ($values as $entry) {
                if (!\is_array($entry)) {
                    continue;
                }
                $value = $entry['value'] ?? null;
                if (\is_string($value) && '' !== $value) {
                    $merged = $this->merge($merged, $this->parseMessage($value));
                }
            }
        }
        $message = $payload['message'] ?? null;
        if (\is_string($message) && '' !== $message) {
            $merged = $this->merge($merged, $this->parseMessage($message));
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $map
     */
    private function fromMap(array $map, string $source): ?QueryFacts
    {
        $sqlstate = $this->stringOrNull($map['sqlstate'] ?? null);
        $code = $this->stringOrNull($map['code'] ?? ($map['vendor_code'] ?? null));
        $driver = $this->stringOrNull($map['driver'] ?? null);
        $sqlMode = $this->stringOrNull($map['sql_mode'] ?? ($map['sqlMode'] ?? null));
        $sql = $this->stringOrNull($map['sql'] ?? ($map['query'] ?? null));
        $bindings = $map['bindings'] ?? null;
        if (!\is_array($bindings)) {
            $bindings = null;
        }

        $facts = new QueryFacts(
            sqlstate: $sqlstate,
            vendorCode: $code,
            driver: $driver,
            sqlMode: $sqlMode,
            sql: $sql,
            bindings: $bindings,
            source: $source,
        );
        if ($facts->isEmpty()) {
            return null;
        }

        return $this->finishSql($facts);
    }

    private function parseMessage(string $message): ?QueryFacts
    {
        $sqlstate = null;
        if (preg_match('/SQLSTATE\[([A-Z0-9]{5})\]/i', $message, $m)) {
            $sqlstate = strtoupper($m[1]);
        }

        $vendorCode = null;
        if (preg_match('/SQLSTATE\[[A-Z0-9]{5}\]\s*:[^:]*:\s*(\d+)/i', $message, $m)) {
            $vendorCode = $m[1];
        } elseif (preg_match('/\((\d{3,5}),\s*[\'"]([^\'"]+)[\'"]\)/', $message, $m)) {
            $vendorCode = $m[1];
        }

        $sqlMode = null;
        if (preg_match('/sql_mode\s*=\s*[\'"]?([^\s,;\'")]+)/i', $message, $m)) {
            $sqlMode = $m[1];
        }

        $driver = null;
        if (preg_match('/Connection:\s*([a-z0-9_]+)/i', $message, $m)) {
            $driver = strtolower($m[1]);
        }

        $sql = null;
        if (preg_match('/\(SQL:\s*(.+)\)\s*$/s', $message, $m) || preg_match('/,\s*SQL:\s*(.+)\)\s*$/s', $message, $m)) {
            $sql = trim($m[1]);
        }

        $facts = new QueryFacts(
            sqlstate: $sqlstate,
            vendorCode: $vendorCode,
            driver: $driver,
            sqlMode: $sqlMode,
            sql: $sql,
            source: QueryFacts::SOURCE_EXCEPTION,
        );
        if ($facts->isEmpty()) {
            return null;
        }

        return $this->finishSql($facts);
    }

    private function finishSql(QueryFacts $facts): QueryFacts
    {
        $sql = $facts->sql;
        $truncated = false;
        if (null !== $sql && mb_strlen($sql) > self::MAX_SQL_DISPLAY) {
            $sql = mb_substr($sql, 0, self::MAX_SQL_DISPLAY);
            $truncated = true;
        }

        return new QueryFacts(
            sqlstate: $facts->sqlstate,
            vendorCode: $facts->vendorCode,
            driver: $facts->driver,
            sqlMode: $facts->sqlMode,
            sql: $sql,
            bindings: $facts->bindings,
            source: $facts->source,
            sqlTruncated: $truncated || $facts->sqlTruncated,
            summary: $facts->summary,
        );
    }

    private function merge(?QueryFacts $base, ?QueryFacts $overlay): ?QueryFacts
    {
        if (null === $overlay) {
            return $base;
        }
        if (null === $base) {
            return $overlay;
        }

        $sql = $overlay->sql ?? $base->sql;
        $truncated = null !== $overlay->sql ? $overlay->sqlTruncated : $base->sqlTruncated;

        return new QueryFacts(
            sqlstate: $overlay->sqlstate ?? $base->sqlstate,
            vendorCode: $overlay->vendorCode ?? $base->vendorCode,
            driver: $overlay->driver ?? $base->driver,
            sqlMode: $overlay->sqlMode ?? $base->sqlMode,
            sql: $sql,
            bindings: $overlay->bindings ?? $base->bindings,
            source: $overlay->source,
            sqlTruncated: $truncated,
            summary: $overlay->summary ?? $base->summary,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function withSummary(QueryFacts $facts, array $payload): QueryFacts
    {
        $raw = $this->preferredExceptionValue($payload) ?? (isset($payload['message']) && \is_string($payload['message']) ? $payload['message'] : '');
        $stripped = preg_replace('/\s*\(Connection:\s*[^,]+,\s*SQL:\s*.+\)\s*$/s', '', $raw) ?? $raw;
        $stripped = preg_replace('/\s*\(SQL:\s*.+\)\s*$/s', '', $stripped) ?? $stripped;
        $stripped = trim($stripped);

        $summary = $stripped;
        if (null !== $facts->sqlstate && !str_contains($stripped, 'SQLSTATE[')) {
            $prefix = 'SQLSTATE['.$facts->sqlstate.']';
            if (null !== $facts->vendorCode) {
                $prefix .= ': '.$facts->vendorCode;
            }
            $summary = '' !== $stripped ? $prefix.': '.$stripped : $prefix;
        }
        if (mb_strlen($summary) > 400) {
            $summary = mb_substr($summary, 0, 400).'…';
        }

        return new QueryFacts(
            sqlstate: $facts->sqlstate,
            vendorCode: $facts->vendorCode,
            driver: $facts->driver,
            sqlMode: $facts->sqlMode,
            sql: $facts->sql,
            bindings: $facts->bindings,
            source: $facts->source,
            sqlTruncated: $facts->sqlTruncated,
            summary: '' !== $summary ? $summary : null,
        );
    }

    /**
     * Prefer the outermost exception message (last Envelope value) for the hero summary.
     *
     * @param array<string, mixed> $payload
     */
    private function preferredExceptionValue(array $payload): ?string
    {
        $values = $payload['exception']['values'] ?? null;
        if (!\is_array($values) || [] === $values) {
            return null;
        }
        $last = $values[array_key_last($values)];
        if (\is_array($last) && isset($last['value']) && \is_string($last['value'])) {
            return $last['value'];
        }

        return null;
    }

    private function looksLikeSql(string $text): bool
    {
        return 1 === preg_match('/^\s*(select|insert|update|delete|with|alter|create|drop|replace|show|explain)\b/i', $text);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!\is_scalar($value)) {
            return null;
        }
        $s = trim((string) $value);

        return '' === $s ? null : $s;
    }
}
