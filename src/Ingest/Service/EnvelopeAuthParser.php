<?php

declare(strict_types=1);

namespace App\Ingest\Service;

/**
 * Extracts public key / secret from Envelope auth mechanisms.
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

        if (null !== $authHeader && str_starts_with($authHeader, 'Beacon ')) {
            $parts = $this->parseAuthPairs(substr($authHeader, 7));
            $key = $parts['beacon_key'] ?? null;
            $secret = $parts['beacon_secret'] ?? null;
        }

        if (null === $key && '' !== $queryString) {
            parse_str($queryString, $query);
            if (isset($query['beacon_key']) && \is_string($query['beacon_key'])) {
                $key = $query['beacon_key'];
            }
            if (isset($query['beacon_secret']) && \is_string($query['beacon_secret'])) {
                $secret = $query['beacon_secret'];
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
