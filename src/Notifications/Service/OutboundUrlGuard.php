<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use InvalidArgumentException;
use App\Shared\Settings\Service\InstanceOpsDefaults;

/**
 * Blocks SSRF to private / link-local / metadata addresses for outbound notification HTTP URLs.
 *
 * When DNS is used, {@see httpClientOptionsForUrl()} pins the first validated public A record via
 * HttpClient `resolve` so a later rebinding cannot steer the TCP connection to a private IP.
 */
final readonly class OutboundUrlGuard
{
    public function __construct(
        private InstanceOpsDefaults $opsDefaults,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the URL is unsafe
     */
    public function assertSafeHttpUrl(string $url): void
    {
        $this->assertSafeAndPin($url);
    }

    /**
     * Validate the URL and return HttpClient options that pin DNS for hostname targets.
     *
     * @return array{resolve?: array<string, string>}
     *
     * @throws InvalidArgumentException when the URL is unsafe
     */
    public function httpClientOptionsForUrl(string $url): array
    {
        $pin = $this->assertSafeAndPin($url);
        if (null === $pin) {
            return [];
        }

        return ['resolve' => [$pin['host'] => $pin['ip']]];
    }

    /**
     * @return array{host: string, ip: string}|null Pin map when a hostname was resolved; null when pin is N/A
     *
     * @throws InvalidArgumentException when the URL is unsafe
     */
    private function assertSafeAndPin(string $url): ?array
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

        if ($this->opsDefaults->allowPrivateUrls()) {
            return null;
        }

        $host = strtolower($parts['host']);
        if ($this->isBlockedHostName($host)) {
            throw new InvalidArgumentException('Notification URL host is not allowed.');
        }

        $ipLiteral = $host;
        if (str_starts_with($ipLiteral, '[') && str_ends_with($ipLiteral, ']')) {
            $ipLiteral = substr($ipLiteral, 1, -1);
        }

        if (false !== filter_var($ipLiteral, \FILTER_VALIDATE_IP)) {
            if ($this->isBlockedIp($ipLiteral)) {
                throw new InvalidArgumentException('Notification URL must not target a private address.');
            }

            return null;
        }

        $ips = gethostbynamel($host);
        if (false === $ips || [] === $ips) {
            throw new InvalidArgumentException('Notification URL host could not be resolved.');
        }

        $publicIps = [];
        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw new InvalidArgumentException('Notification URL resolves to a private address.');
            }
            $publicIps[] = $ip;
        }

        // Pin the first validated public A record for the outbound connect (anti DNS rebinding).
        return ['host' => $host, 'ip' => $publicIps[0]];
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
