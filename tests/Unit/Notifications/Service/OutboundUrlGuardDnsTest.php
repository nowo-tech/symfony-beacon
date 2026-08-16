<?php

declare(strict_types=1);

namespace App\Notifications\Service {
    final class OutboundUrlGuardDnsHooks
    {
        public static $dnsGetRecord;
        public static $getHostByNameL;

        public static function reset(): void
        {
            self::$dnsGetRecord = null;
            self::$getHostByNameL = null;
        }
    }

    function dns_get_record(string $hostname, int $type = \DNS_ANY): array|false
    {
        return \is_callable(OutboundUrlGuardDnsHooks::$dnsGetRecord)
            ? (OutboundUrlGuardDnsHooks::$dnsGetRecord)($hostname, $type)
            : \dns_get_record($hostname, $type);
    }

    function gethostbynamel(string $hostname): array|false
    {
        return \is_callable(OutboundUrlGuardDnsHooks::$getHostByNameL)
            ? (OutboundUrlGuardDnsHooks::$getHostByNameL)($hostname)
            : \gethostbynamel($hostname);
    }
}

namespace App\Tests\Unit\Notifications\Service {
    use App\Notifications\Service\OutboundUrlGuard;
    use App\Notifications\Service\OutboundUrlGuardDnsHooks;
    use App\Shared\Settings\Entity\InstanceSettings;
    use App\Shared\Settings\Repository\InstanceSettingsRepository;
    use App\Shared\Settings\Service\InstanceOpsDefaults;
    use InvalidArgumentException;
    use PHPUnit\Framework\TestCase;

    final class OutboundUrlGuardDnsTest extends TestCase
    {
        protected function tearDown(): void
        {
            OutboundUrlGuardDnsHooks::reset();
        }

        public function testUsesAaaaRecordsAndFallbackResolutionAndRejectsBadAnswers(): void
        {
            $guard = $this->guard();

            OutboundUrlGuardDnsHooks::$dnsGetRecord = (static fn (string $host, int $type): array|false => match ($type) {
                \DNS_A => [],
                \DNS_AAAA => [['ipv6' => '2606:4700:4700::1111']],
                default => [],
            });
            $options = $guard->httpClientOptionsForUrl('https://example.test/hook');
            self::assertSame('2606:4700:4700::1111', $options['resolve']['example.test']);

            OutboundUrlGuardDnsHooks::$dnsGetRecord = static fn (string $host, int $type): array|false => [];
            OutboundUrlGuardDnsHooks::$getHostByNameL = static fn (string $host): array|false => ['8.8.8.8'];
            $fallback = $guard->httpClientOptionsForUrl('https://fallback.test/hook');
            self::assertSame('8.8.8.8', $fallback['resolve']['fallback.test']);

            OutboundUrlGuardDnsHooks::$getHostByNameL = static fn (string $host): array|false => ['127.0.0.1'];
            try {
                $guard->httpClientOptionsForUrl('https://private.test/hook');
                self::fail('Expected private resolution to be rejected');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('private address', $e->getMessage());
            }

            OutboundUrlGuardDnsHooks::$getHostByNameL = static fn (string $host): array|false => ['not-an-ip'];
            try {
                $guard->httpClientOptionsForUrl('https://invalid.test/hook');
                self::fail('Expected invalid resolution to be rejected');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('private address', $e->getMessage());
            }
        }

        private function guard(): OutboundUrlGuard
        {
            $settings = InstanceSettings::defaults()->setAllowPrivateUrls(false);
            $repo = $this->createStub(InstanceSettingsRepository::class);
            $repo->method('getOrCreate')->willReturn($settings);

            return new OutboundUrlGuard(new InstanceOpsDefaults($repo));
        }
    }
}
