<?php

declare(strict_types=1);

namespace App\Issues\Export;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;

/**
 * Builds versioned {@see FORMAT} documents for pasting an issue into an external AI assistant.
 *
 * @phpstan-type AiExportArray array{
 *     format: string,
 *     issue: array<string, mixed>,
 *     event: array<string, mixed>|null,
 *     exception: array<string, mixed>|null,
 *     stacktrace: list<array<string, mixed>>,
 *     request: array<string, mixed>|null,
 *     tags: array<string, mixed>,
 *     breadcrumbs: list<array<string, mixed>>,
 *     links: array{issue: string}
 * }
 */
final class AiIssueExportFormatter
{
    public const string FORMAT = 'beacon-ai-export/v1';

    /** @var list<string> */
    private const array SENSITIVE_HEADERS = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-beacon-auth',
        'proxy-authorization',
        'x-api-key',
        'x-auth-token',
    ];

    /**
     * @return AiExportArray
     */
    public function buildCanonical(Project $project, Issue $issue, ?Event $event, string $issueAbsoluteUrl): array
    {
        $payload = $event?->getPayload() ?? [];
        $exception = $this->extractException($payload);
        $frames = $this->extractFrames($payload);
        $request = $this->scrubRequest($this->extractRequest($payload));
        $tags = $this->normalizeTags($payload['tags'] ?? []);
        $breadcrumbs = $this->summarizeBreadcrumbs($payload['breadcrumbs'] ?? null);

        return [
            'format' => self::FORMAT,
            'issue' => [
                'uuid' => $issue->getUuid(),
                'title' => $issue->getTitle(),
                'culprit' => $issue->getCulprit(),
                'level' => $issue->getLevel(),
                'status' => $issue->getStatus()->value,
                'priority' => $issue->getPriority()->value,
                'fingerprint' => $issue->getFingerprint(),
                'event_count' => $issue->getEventCount(),
                'first_seen' => $issue->getFirstSeen()->format(\DATE_ATOM),
                'last_seen' => $issue->getLastSeen()->format(\DATE_ATOM),
                'project' => [
                    'uuid' => $project->getUuid(),
                    'name' => $project->getName(),
                    'slug' => $project->getSlug(),
                ],
            ],
            'event' => $event instanceof Event ? [
                'event_id' => $event->getEventId(),
                'environment' => $event->getEnvironment(),
                'release' => $event->getReleaseVersion(),
                'platform' => $event->getPlatform(),
                'timestamp' => $event->getEventTimestamp()->format(\DATE_ATOM),
                'received_at' => $event->getReceivedAt()->format(\DATE_ATOM),
                'message' => isset($payload['message']) && \is_string($payload['message']) ? $payload['message'] : null,
            ] : null,
            'exception' => $exception,
            'stacktrace' => $frames,
            'request' => $request,
            'tags' => $tags,
            'breadcrumbs' => $breadcrumbs,
            'links' => [
                'issue' => $issueAbsoluteUrl,
            ],
        ];
    }

    /**
     * @param AiExportArray $data
     */
    public function toJson(array $data): string
    {
        return json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @param AiExportArray $data
     */
    public function toMarkdown(array $data): string
    {
        $issue = $data['issue'];
        $event = $data['event'];
        $exception = $data['exception'];
        $headlineType = \is_array($exception) && isset($exception['type']) && \is_string($exception['type'])
            ? $exception['type']
            : (string) $issue['title'];
        $headlineDetail = \is_array($exception) && isset($exception['value']) && \is_string($exception['value'])
            ? $exception['value']
            : (string) ($issue['culprit'] ?: $issue['title']);

        $env = \is_array($event) ? (string) ($event['environment'] ?? '') : '';
        $release = \is_array($event) ? (string) ($event['release'] ?? '') : '';

        $lines = [
            '---',
            'format: '.self::FORMAT,
            'issue_id: '.$issue['uuid'],
            'project: '.$issue['project']['slug'],
            'level: '.$issue['level'],
            'status: '.$issue['status'],
            'environment: '.('' !== $env ? $env : '—'),
            'release: '.('' !== $release ? $release : '—'),
            '---',
            '',
            '# '.$headlineType.': '.$headlineDetail,
            '',
            '## Summary',
            '',
            '- Title: '.$issue['title'],
            '- Culprit: '.('' !== $issue['culprit'] ? $issue['culprit'] : '—'),
            '- Level: '.$issue['level'],
            '- Status: '.$issue['status'],
            '- Priority: '.$issue['priority'],
            '- Fingerprint: `'.$issue['fingerprint'].'`',
            '- Events: '.$issue['event_count'],
            '- First seen: '.$issue['first_seen'],
            '- Last seen: '.$issue['last_seen'],
            '',
        ];

        $lines[] = '## Exception';
        $lines[] = '';
        if (\is_array($exception)) {
            $lines[] = '- Type: `'.($exception['type'] ?? '—').'`';
            $lines[] = '- Message: '.($exception['value'] ?? '—');
        } elseif (\is_array($event) && isset($event['message']) && \is_string($event['message'])) {
            $lines[] = '- Message: '.$event['message'];
        } else {
            $lines[] = '_No exception payload._';
        }
        $lines[] = '';

        $lines[] = '## Stacktrace';
        $lines[] = '';
        if ([] === $data['stacktrace']) {
            $lines[] = '_No frames._';
        } else {
            $lines[] = '```';
            foreach ($data['stacktrace'] as $i => $frame) {
                $file = (string) ($frame['filename'] ?? $frame['abs_path'] ?? '?');
                $line = $frame['lineno'] ?? '?';
                $fn = (string) ($frame['function'] ?? '');
                $lines[] = \sprintf('#%d %s:%s %s', $i, $file, $line, $fn);
            }
            $lines[] = '```';
        }
        $lines[] = '';

        $lines[] = '## Request';
        $lines[] = '';
        if (null === $data['request']) {
            $lines[] = '_No request context._';
        } else {
            $req = $data['request'];
            $lines[] = '- Method: '.($req['method'] ?? '—');
            $lines[] = '- URL: '.($req['url'] ?? $req['path'] ?? '—');
            if (isset($req['headers']) && \is_array($req['headers']) && [] !== $req['headers']) {
                $lines[] = '- Headers:';
                foreach ($req['headers'] as $name => $value) {
                    $lines[] = '  - '.$name.': '.(\is_scalar($value) ? (string) $value : json_encode($value));
                }
            }
        }
        $lines[] = '';

        $lines[] = '## Tags / Context';
        $lines[] = '';
        if ([] === $data['tags']) {
            $lines[] = '_No tags._';
        } else {
            foreach ($data['tags'] as $key => $value) {
                $lines[] = '- '.$key.': '.(\is_scalar($value) ? (string) $value : json_encode($value));
            }
        }
        $lines[] = '';

        $lines[] = '## Recent breadcrumbs';
        $lines[] = '';
        if ([] === $data['breadcrumbs']) {
            $lines[] = '_No breadcrumbs._';
        } else {
            foreach ($data['breadcrumbs'] as $crumb) {
                $cat = (string) ($crumb['category'] ?? '');
                $msg = (string) ($crumb['message'] ?? $crumb['type'] ?? '');
                $lines[] = '- ['.$cat.'] '.$msg;
            }
        }
        $lines[] = '';

        $lines[] = '## Links';
        $lines[] = '';
        $lines[] = '- Issue: '.$data['links']['issue'];
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{type?: string, value?: string}|null
     */
    private function extractException(array $payload): ?array
    {
        $values = $payload['exception']['values'] ?? null;
        if (!\is_array($values) || !isset($values[0]) || !\is_array($values[0])) {
            return null;
        }
        $ex = $values[0];
        $out = [];
        if (isset($ex['type']) && \is_string($ex['type'])) {
            $out['type'] = $ex['type'];
        }
        if (isset($ex['value']) && \is_string($ex['value'])) {
            $out['value'] = $ex['value'];
        }

        return [] === $out ? null : $out;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function extractFrames(array $payload): array
    {
        $values = $payload['exception']['values'] ?? null;
        $frames = [];
        if (\is_array($values) && isset($values[0]) && \is_array($values[0])) {
            $stack = $values[0]['stacktrace']['frames'] ?? null;
            if (\is_array($stack)) {
                $frames = $stack;
            }
        }
        if ([] === $frames) {
            $stack = $payload['stacktrace']['frames'] ?? null;
            if (\is_array($stack)) {
                $frames = $stack;
            }
        }

        $normalized = [];
        foreach ($frames as $frame) {
            if (!\is_array($frame)) {
                continue;
            }
            $normalized[] = [
                'filename' => $frame['filename'] ?? null,
                'abs_path' => $frame['abs_path'] ?? null,
                'lineno' => $frame['lineno'] ?? null,
                'function' => $frame['function'] ?? null,
                'in_app' => $frame['in_app'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function extractRequest(array $payload): ?array
    {
        $request = $payload['request'] ?? $payload['contexts']['request'] ?? null;

        return \is_array($request) ? $request : null;
    }

    /**
     * @param array<string, mixed>|null $request
     *
     * @return array<string, mixed>|null
     */
    private function scrubRequest(?array $request): ?array
    {
        if (null === $request) {
            return null;
        }

        $out = $request;
        if (isset($out['headers']) && \is_array($out['headers'])) {
            $out['headers'] = $this->scrubHeaders($out['headers']);
        }
        if (isset($out['cookies'])) {
            $out['cookies'] = '[redacted]';
        }
        if (isset($out['data']) && \is_array($out['data'])) {
            $out['data'] = $this->scrubAssocSecrets($out['data']);
        }

        return $out;
    }

    /**
     * @param array<mixed> $headers
     *
     * @return array<string, mixed>
     */
    private function scrubHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $key = \is_string($name) ? $name : (string) $name;
            if (\in_array(strtolower($key), self::SENSITIVE_HEADERS, true)) {
                $out[$key] = '[redacted]';
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private function scrubAssocSecrets(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $name = \is_string($key) ? strtolower($key) : '';
            if ('' !== $name && (str_contains($name, 'password') || str_contains($name, 'secret') || str_contains($name, 'token') || str_contains($name, 'authorization'))) {
                $out[$key] = '[redacted]';
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeTags(mixed $tags): array
    {
        if (!\is_array($tags)) {
            return [];
        }
        $out = [];
        foreach ($tags as $key => $value) {
            if (\is_int($key) && \is_array($value) && isset($value[0], $value[1])) {
                $out[(string) $value[0]] = $value[1];
                continue;
            }
            if (\is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function summarizeBreadcrumbs(mixed $breadcrumbs): array
    {
        $values = [];
        if (\is_array($breadcrumbs)) {
            $values = isset($breadcrumbs['values']) && \is_array($breadcrumbs['values'])
                ? $breadcrumbs['values']
                : $breadcrumbs;
        }

        $out = [];
        $count = 0;
        foreach ($values as $crumb) {
            if (!\is_array($crumb)) {
                continue;
            }
            $out[] = [
                'type' => $crumb['type'] ?? null,
                'category' => $crumb['category'] ?? null,
                'message' => $crumb['message'] ?? null,
                'level' => $crumb['level'] ?? null,
                'timestamp' => $crumb['timestamp'] ?? null,
            ];
            ++$count;
            if ($count >= 25) {
                break;
            }
        }

        return $out;
    }
}
