<?php

declare(strict_types=1);

namespace App\Notifications\Service;

/**
 * DNS lookup seam for outbound URL SSRF checks (mockable in unit tests).
 */
class HostnameDnsLookup
{
    /**
     * @return array<int, array<string, mixed>>|false
     */
    public function dnsGetRecord(string $hostname, int $type): array|false
    {
        return @\dns_get_record($hostname, $type);
    }

    /**
     * @return list<string>|false
     */
    public function hostByNameL(string $hostname): array|false
    {
        return @\gethostbynamel($hostname);
    }
}
