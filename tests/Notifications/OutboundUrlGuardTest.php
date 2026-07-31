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
        self::assertSame([], $guard->httpClientOptionsForUrl('https://127.0.0.1/hook'));
    }

    public function testAllowsPublicHttpsHostAndPinsResolve(): void
    {
        $guard = new OutboundUrlGuard(allowPrivateUrls: false);
        $options = $guard->httpClientOptionsForUrl('https://example.com/webhook');

        self::assertArrayHasKey('resolve', $options);
        self::assertArrayHasKey('example.com', $options['resolve']);
        self::assertNotFalse(filter_var($options['resolve']['example.com'], \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4));
        self::assertFalse(
            false === filter_var(
                $options['resolve']['example.com'],
                \FILTER_VALIDATE_IP,
                \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
            ),
        );
    }

    public function testLiteralPublicIpNeedsNoResolvePin(): void
    {
        $guard = new OutboundUrlGuard(allowPrivateUrls: false);
        // 1.1.1.1 is public; no DNS pin required.
        self::assertSame([], $guard->httpClientOptionsForUrl('https://1.1.1.1/hook'));
    }
}
