<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Notifications\Service\OutboundUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Tests\Shared\InstanceOpsDefaultsTestTrait;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OutboundUrlGuardTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testBlocksPrivateIpLiteral(): void
    {
        $guard = $this->guard(false);

        $this->expectException(InvalidArgumentException::class);
        $guard->assertSafeHttpUrl('https://127.0.0.1/hook');
    }

    public function testBlocksMetadataHost(): void
    {
        $guard = $this->guard(false);

        $this->expectException(InvalidArgumentException::class);
        $guard->assertSafeHttpUrl('http://169.254.169.254/latest/meta-data/');
    }

    public function testAllowsWhenPrivateUrlsEnabled(): void
    {
        $guard = $this->guard(true);
        $guard->assertSafeHttpUrl('https://127.0.0.1/hook');
        self::assertSame([], $guard->httpClientOptionsForUrl('https://127.0.0.1/hook'));
    }

    public function testAllowsPublicHttpsHostAndPinsResolve(): void
    {
        $guard = $this->guard(false);
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
        $guard = $this->guard(false);
        // 1.1.1.1 is public; no DNS pin required.
        self::assertSame([], $guard->httpClientOptionsForUrl('https://1.1.1.1/hook'));
    }

    public function testLiteralPublicIpv6NeedsNoResolvePin(): void
    {
        $guard = $this->guard(false);
        self::assertSame([], $guard->httpClientOptionsForUrl('https://[2606:4700:4700::1111]/hook'));
    }

    public function testBlocksUniqueLocalIpv6(): void
    {
        $guard = $this->guard(false);

        $this->expectException(InvalidArgumentException::class);
        $guard->assertSafeHttpUrl('https://[fd12:3456:789a::1]/hook');
    }

    private function guard(bool $allowPrivateUrls): OutboundUrlGuard
    {
        return new OutboundUrlGuard($this->opsDefaultsWith(static function (InstanceSettings $settings) use ($allowPrivateUrls): void {
            $settings->setAllowPrivateUrls($allowPrivateUrls);
        }));
    }
}
