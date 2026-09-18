<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;

/**
 * In-process DNS for FrankenPHP, where the kit's child `php -r` cannot start.
 */
final class InProcessHostnameDnsLookup extends HostnameDnsLookup
{
    public function dnsGetRecord(string $hostname, int $type): array|false
    {
        $records = @\dns_get_record($hostname, $type);

        return \is_array($records) ? $records : false;
    }

    public function hostByNameL(string $hostname): array|false
    {
        $ips = @\gethostbynamel($hostname);

        return \is_array($ips) ? $ips : false;
    }
}
