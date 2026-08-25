<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Notifications\Service\OutboundUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OutboundUrlGuardTest extends TestCase
{
    public function testRejectsInvalidSchemesBlockedHostsAndPrivateIps(): void
    {
        $guard = $this->guard(false);

        foreach (['not-a-url', 'ftp://example.com/file', 'https://localhost/hook', 'http://127.0.0.1/hook', 'http://[::1]/hook'] as $url) {
            try {
                $guard->assertSafeHttpUrl($url);
                self::fail('Expected InvalidArgumentException for '.$url);
            } catch (InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testAllowsPrivateUrlsWhenInstanceSettingEnablesThem(): void
    {
        $guard = $this->guard(true);

        $guard->assertSafeHttpUrl('https://localhost/hook');
        self::assertSame([], $guard->httpClientOptionsForUrl('http://127.0.0.1/hook'));
    }

    public function testPinsPublicHostsAndRejectsUnresolvableOnes(): void
    {
        $guard = $this->guard(false);

        $options = $guard->httpClientOptionsForUrl('https://example.com/webhook');
        self::assertSame('example.com', array_key_first($options['resolve']));
        self::assertNotFalse(filter_var($options['resolve']['example.com'], \FILTER_VALIDATE_IP));
        self::assertSame([], $guard->httpClientOptionsForUrl('https://1.1.1.1/webhook'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('could not be resolved');
        $guard->httpClientOptionsForUrl('https://nonexistent.invalid/webhook');
    }

    private function guard(bool $allowPrivate): OutboundUrlGuard
    {
        $settings = InstanceSettings::defaults()->setAllowPrivateUrls($allowPrivate);
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        return new OutboundUrlGuard(new InstanceOpsDefaults($repo));
    }
}
