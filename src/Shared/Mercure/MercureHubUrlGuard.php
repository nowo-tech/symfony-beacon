<?php

declare(strict_types=1);

namespace App\Shared\Mercure;

final class MercureHubUrlGuard
{
    public const string RESULT_VALID = 'valid';
    public const string RESULT_INVALID = 'invalid';
    public const string RESULT_UNSAFE = 'unsafe';

    public function classifyHttpUrl(?string $value): string
    {
        $trimmed = trim((string) $value);
        if ('' === $trimmed) {
            return self::RESULT_INVALID;
        }

        $parts = parse_url($trimmed);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host']) || !\is_string($parts['scheme']) || !\is_string($parts['host'])) {
            return self::RESULT_INVALID;
        }

        $scheme = strtolower($parts['scheme']);
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return self::RESULT_INVALID;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ('' === $host || $this->isBlockedHost($host)) {
            return self::RESULT_UNSAFE;
        }

        $ipLiteral = $host;
        if (str_starts_with($ipLiteral, '[') && str_ends_with($ipLiteral, ']')) {
            $ipLiteral = substr($ipLiteral, 1, -1);
        }

        if (false !== filter_var($ipLiteral, \FILTER_VALIDATE_IP)) {
            return $this->isBlockedIp($ipLiteral)
                ? self::RESULT_UNSAFE
                : self::RESULT_VALID;
        }

        return self::RESULT_VALID;
    }

    public function isSafeHttpUrl(?string $value): bool
    {
        return self::RESULT_VALID === $this->classifyHttpUrl($value);
    }

    private function isBlockedHost(string $host): bool
    {
        return \in_array($host, [
            'metadata',
            'metadata.google.internal',
        ], true);
    }

    private function isBlockedIp(string $ip): bool
    {
        if (false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            $packed = @inet_pton($ip);

            return false !== $packed && str_starts_with($packed, "\xA9\xFE");
        }

        if (false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if (false === $packed) {
                return true; // @codeCoverageIgnore
            }

            // fe80::/10 link-local (including metadata-accessible variants).
            return 0xFE === \ord($packed[0]) && 0x80 === (\ord($packed[1]) & 0xC0);
        }

        return true;
    }
}
