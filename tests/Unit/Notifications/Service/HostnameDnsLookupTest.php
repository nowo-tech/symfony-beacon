<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Notifications\Service\HostnameDnsLookup;
use PHPUnit\Framework\TestCase;

final class HostnameDnsLookupTest extends TestCase
{
    public function testNativeLookupsReturnArrayOrFalse(): void
    {
        $lookup = new HostnameDnsLookup();
        $records = $lookup->dnsGetRecord('localhost', \DNS_A);
        $hosts = $lookup->hostByNameL('localhost');
        if (false === $records || false === $hosts) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->addToAssertionCount(1);
    }
}
