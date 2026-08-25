<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Shared\Http\PrivateNetworkTarget;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use InvalidArgumentException;

/**
 * Blocks SSRF to private / link-local / metadata addresses for outbound notification HTTP URLs.
 *
 * When DNS is used, {@see httpClientOptionsForUrl()} validates public **A and AAAA** answers, then
 * pins the first public IPv4 (preferred) or IPv6 via HttpClient `resolve` so a later rebinding
 * cannot steer the TCP connection to a private IP.
 */
final readonly class OutboundUrlGuard
{
    public function __construct(
        private InstanceOpsDefaults $opsDefaults,
        private HostnameDnsLookup $dnsLookup = new HostnameDnsLookup(),
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
        if (PrivateNetworkTarget::isBlockedHostName($host)) {
            throw new InvalidArgumentException('Notification URL host is not allowed.');
        }

        $ipLiteral = $host;
        if (str_starts_with($ipLiteral, '[') && str_ends_with($ipLiteral, ']')) {
            $ipLiteral = substr($ipLiteral, 1, -1);
        }

        if (false !== filter_var($ipLiteral, \FILTER_VALIDATE_IP)) {
            if (PrivateNetworkTarget::isBlockedIp($ipLiteral)) {
                throw new InvalidArgumentException('Notification URL must not target a private address.');
            }

            return null;
        }

        $ips = $this->resolvePublicIps($host);
        if ([] === $ips) {
            throw new InvalidArgumentException('Notification URL host could not be resolved.');
        }

        // Prefer an IPv4 pin when both families are public (HttpClient resolve + dual-stack).
        $pinIp = $ips[0];
        foreach ($ips as $ip) {
            if (false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
                $pinIp = $ip;
                break;
            }
        }

        return ['host' => $host, 'ip' => $pinIp];
    }

    /**
     * Resolve hostname to public A + AAAA addresses. Private answers fail closed.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when any answer is private/reserved
     */
    private function resolvePublicIps(string $host): array
    {
        $candidates = [];

        $aRecords = $this->dnsLookup->dnsGetRecord($host, \DNS_A);
        if (\is_array($aRecords)) {
            foreach ($aRecords as $row) {
                if (isset($row['ip']) && \is_string($row['ip']) && '' !== $row['ip']) {
                    $candidates[] = $row['ip'];
                }
            }
        }

        $aaaaRecords = $this->dnsLookup->dnsGetRecord($host, \DNS_AAAA);
        if (\is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $row) {
                if (isset($row['ipv6']) && \is_string($row['ipv6']) && '' !== $row['ipv6']) {
                    $candidates[] = $row['ipv6'];
                }
            }
        }

        // Fallback when dns_get_record is unavailable / empty (common in some containers).
        if ([] === $candidates) {
            $fallback = $this->dnsLookup->hostByNameL($host);
            if (\is_array($fallback)) {
                $candidates = $fallback;
            }
        }

        $publicIps = [];
        foreach (array_values(array_unique($candidates)) as $ip) {
            if (PrivateNetworkTarget::isBlockedIp($ip)) {
                throw new InvalidArgumentException('Notification URL resolves to a private address.');
            }
            $publicIps[] = $ip;
        }

        return $publicIps;
    }
}
