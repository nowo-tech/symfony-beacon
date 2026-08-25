<?php

declare(strict_types=1);

namespace App\Shared\Mercure;

use App\Shared\Http\PrivateNetworkTarget;
use App\Shared\Settings\Service\InstanceOpsDefaults;

/**
 * Validates Mercure hub / public HTTP URLs stored in Administration or env.
 *
 * Policy matches {@see \App\Notifications\Service\OutboundUrlGuard} for IP literals and
 * blocked hostnames. Unlike webhooks, hostnames are **not** DNS-resolved here so Docker
 * Compose service names ({@code mercure}, {@code php}) remain valid without requiring
 * {@see InstanceOpsDefaults::allowPrivateUrls()}.
 *
 * Cloud metadata hosts/IPs are always rejected, even when private URLs are opted in.
 */
final readonly class MercureHubUrlGuard
{
    public const string RESULT_VALID = 'valid';
    public const string RESULT_INVALID = 'invalid';
    public const string RESULT_UNSAFE = 'unsafe';

    public function __construct(
        private ?InstanceOpsDefaults $opsDefaults = null,
    ) {
    }

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
        if ('' === $host) {
            return self::RESULT_UNSAFE;
        }

        $allowPrivate = null !== $this->opsDefaults && $this->opsDefaults->allowPrivateUrls();

        if (PrivateNetworkTarget::isCloudMetadataHost($host)) {
            return self::RESULT_UNSAFE;
        }

        if (!$allowPrivate && PrivateNetworkTarget::isBlockedHostName($host)) {
            return self::RESULT_UNSAFE;
        }

        $ipLiteral = $host;
        if (str_starts_with($ipLiteral, '[') && str_ends_with($ipLiteral, ']')) {
            $ipLiteral = substr($ipLiteral, 1, -1);
        }

        if (false !== filter_var($ipLiteral, \FILTER_VALIDATE_IP)) {
            if (PrivateNetworkTarget::isCloudMetadataIp($ipLiteral)) {
                return self::RESULT_UNSAFE;
            }

            if (!$allowPrivate && PrivateNetworkTarget::isBlockedIp($ipLiteral)) {
                return self::RESULT_UNSAFE;
            }

            return self::RESULT_VALID;
        }

        // Hostname (e.g. Docker service): no DNS resolve — private A/AAAA would break Compose hubs.
        return self::RESULT_VALID;
    }

    public function isSafeHttpUrl(?string $value): bool
    {
        return self::RESULT_VALID === $this->classifyHttpUrl($value);
    }
}
