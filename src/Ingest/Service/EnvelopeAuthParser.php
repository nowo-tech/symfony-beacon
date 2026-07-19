<?php

declare(strict_types=1);

namespace App\Ingest\Service;

/**
 * Extracts sentry_key / project hints from Envelope auth mechanisms.
 */
final class EnvelopeAuthParser
{
    /**
     * @return array{sentry_key: ?string, sentry_secret: ?string}
     */
    public function parseFromRequest(?string $authHeader, string $queryString, ?string $envelopeDsn = null): array
    {
        $key = null;
        $secret = null;

        if (null !== $authHeader && str_starts_with($authHeader, 'Sentry ')) {
            $parts = $this->parseAuthPairs(substr($authHeader, 7));
            $key = $parts['sentry_key'] ?? null;
            $secret = $parts['sentry_secret'] ?? null;
        }

        if (null === $key && '' !== $queryString) {
            parse_str($queryString, $query);
            if (isset($query['sentry_key']) && \is_string($query['sentry_key'])) {
                $key = $query['sentry_key'];
            }
            if (isset($query['sentry_secret']) && \is_string($query['sentry_secret'])) {
                $secret = $query['sentry_secret'];
            }
        }

        if (null === $key && null !== $envelopeDsn && '' !== $envelopeDsn) {
            $parsed = parse_url($envelopeDsn);
            if (isset($parsed['user']) && \is_string($parsed['user'])) {
                $key = $parsed['user'];
            }
            if (isset($parsed['pass']) && \is_string($parsed['pass'])) {
                $secret = $parsed['pass'];
            }
        }

        return ['sentry_key' => $key, 'sentry_secret' => $secret];
    }

    /**
     * @return array<string, string>
     */
    private function parseAuthPairs(string $raw): array
    {
        $result = [];
        foreach (explode(',', $raw) as $chunk) {
            $chunk = trim($chunk);
            if (!str_contains($chunk, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $chunk, 2);
            $result[trim($k)] = trim($v);
        }

        return $result;
    }
}
