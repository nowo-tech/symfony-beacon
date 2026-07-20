<?php

declare(strict_types=1);

namespace App\Ingest\Service;

/**
 * Extracts public key / secret from Envelope auth mechanisms.
 *
 * Accepts historical Envelope wire field names in headers and query strings for client compatibility.
 */
final class EnvelopeAuthParser
{
    /**
     * @return array{public_key: ?string, secret_key: ?string}
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

        return ['public_key' => $key, 'secret_key' => $secret];
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
