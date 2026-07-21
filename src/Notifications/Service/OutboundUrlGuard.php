<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use InvalidArgumentException;

/**
 * Blocks SSRF to private / link-local / metadata addresses for outbound notification HTTP URLs.
 */
final readonly class OutboundUrlGuard
{
    public function __construct(
        private bool $allowPrivateUrls = false,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the URL is unsafe
     */
    public function assertSafeHttpUrl(string $url): void
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host']) || !\is_string($parts['scheme']) || !\is_string($parts['host'])) {
            throw new InvalidArgumentException('Invalid notification URL.');
        }

        $scheme = strtolower($parts['scheme']);
        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Notification URL must use http or https.');
        }

        if ($this->allowPrivateUrls) {
            return;
        }

        $host = strtolower($parts['host']);
        if ($this->isBlockedHostName($host)) {
            throw new InvalidArgumentException('Notification URL host is not allowed.');
        }

        if (false !== filter_var($host, \FILTER_VALIDATE_IP)) {
            if ($this->isBlockedIp($host)) {
                throw new InvalidArgumentException('Notification URL must not target a private address.');
            }

            return;
        }

        $ips = gethostbynamel($host);
        if (false === $ips || [] === $ips) {
            throw new InvalidArgumentException('Notification URL host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw new InvalidArgumentException('Notification URL resolves to a private address.');
            }
        }
    }

    private function isBlockedHostName(string $host): bool
    {
        return 'localhost' === $host
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || 'metadata.google.internal' === $host;
    }

    private function isBlockedIp(string $ip): bool
    {
        if (false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            return true;
        }

        // Block loopback, RFC1918, link-local, unique-local, multicast, unspecified.
        return false === filter_var(
            $ip,
            \FILTER_VALIDATE_IP,
            \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
        );
    }
}
