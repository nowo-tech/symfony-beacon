<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Notifications\Service\HostnameDnsLookup;
use App\Notifications\Service\OutboundUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OutboundUrlGuardDnsTest extends TestCase
{
    public function testUsesAaaaRecordsAndFallbackResolutionAndRejectsBadAnswers(): void
    {
        /** @var HostnameDnsLookup&MockObject $dns */
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->method('dnsGetRecord')->willReturnCallback(
            static fn (string $host, int $type): array => match ($host) {
                'example.test' => \DNS_AAAA === $type ? [['ipv6' => '2606:4700:4700::1111']] : [],
                default => [],
            },
        );
        $dns->method('hostByNameL')->willReturnCallback(
            static fn (string $host): array|false => match ($host) {
                'fallback.test' => ['8.8.8.8'],
                'private.test' => ['127.0.0.1'],
                'invalid.test' => ['not-an-ip'],
                default => false,
            },
        );

        $guard = $this->guard($dns);

        $options = $guard->httpClientOptionsForUrl('https://example.test/hook');
        self::assertSame('2606:4700:4700::1111', $options['resolve']['example.test']);

        $fallback = $guard->httpClientOptionsForUrl('https://fallback.test/hook');
        self::assertSame('8.8.8.8', $fallback['resolve']['fallback.test']);

        try {
            $guard->httpClientOptionsForUrl('https://private.test/hook');
            self::fail('Expected private resolution to be rejected');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('private address', $e->getMessage());
        }

        try {
            $guard->httpClientOptionsForUrl('https://invalid.test/hook');
            self::fail('Expected invalid resolution to be rejected');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('private address', $e->getMessage());
        }
    }

    private function guard(HostnameDnsLookup $dns): OutboundUrlGuard
    {
        $settings = InstanceSettings::defaults()->setAllowPrivateUrls(false);
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        return new OutboundUrlGuard(new InstanceOpsDefaults($repo), $dns);
    }
}
