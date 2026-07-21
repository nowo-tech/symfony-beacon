<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Notifications\Service\OutboundUrlGuard;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OutboundUrlGuardTest extends TestCase
{
    public function testBlocksPrivateIpLiteral(): void
    {
        $guard = new OutboundUrlGuard(allowPrivateUrls: false);

        $this->expectException(InvalidArgumentException::class);
        $guard->assertSafeHttpUrl('https://127.0.0.1/hook');
    }

    public function testBlocksMetadataHost(): void
    {
        $guard = new OutboundUrlGuard(allowPrivateUrls: false);

        $this->expectException(InvalidArgumentException::class);
        $guard->assertSafeHttpUrl('http://169.254.169.254/latest/meta-data/');
    }

    public function testAllowsWhenPrivateUrlsEnabled(): void
    {
        $guard = new OutboundUrlGuard(allowPrivateUrls: true);
        $guard->assertSafeHttpUrl('https://127.0.0.1/hook');
        $this->addToAssertionCount(1);
    }

    public function testAllowsPublicHttpsHost(): void
    {
        $guard = new OutboundUrlGuard(allowPrivateUrls: false);
        $guard->assertSafeHttpUrl('https://example.com/webhook');
        $this->addToAssertionCount(1);
    }
}
