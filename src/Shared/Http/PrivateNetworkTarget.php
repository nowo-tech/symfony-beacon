<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Shared private / reserved / cloud-metadata target checks for outbound URL guards.
 *
 * Used by webhook SSRF protection and Mercure hub URL validation so both apply
 * the same IP and hostname policy. Callers decide whether to DNS-resolve hostnames
 * (webhooks do; Mercure does not, so Docker service names like {@code mercure} stay usable).
 */
final class PrivateNetworkTarget
{
    public static function isBlockedHostName(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        return 'localhost' === $host
            || 'metadata' === $host
            || 'metadata.google.internal' === $host
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal');
    }

    /**
     * True for loopback, RFC1918, link-local, unique-local, multicast, unspecified, and invalid IPs.
     */
    public static function isBlockedIp(string $ip): bool
    {
        if (false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            return true;
        }

        return false === filter_var(
            $ip,
            \FILTER_VALIDATE_IP,
            \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
        );
    }

    /**
     * Cloud instance-metadata endpoints — always unsafe even when private URLs are opted in.
     */
    public static function isCloudMetadataIp(string $ip): bool
    {
        if (false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            return false;
        }

        if (false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            $packed = @inet_pton($ip);

            // 169.254.0.0/16 link-local (includes 169.254.169.254).
            return false !== $packed && str_starts_with($packed, "\xA9\xFE");
        }

        $packed = @inet_pton($ip);
        if (false === $packed) {
            return true;
        }

        // fe80::/10 link-local.
        return 0xFE === \ord($packed[0]) && 0x80 === (\ord($packed[1]) & 0xC0);
    }

    public static function isCloudMetadataHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        return 'metadata' === $host || 'metadata.google.internal' === $host;
    }
}
