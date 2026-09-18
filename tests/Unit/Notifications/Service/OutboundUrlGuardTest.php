<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Notifications\Service\InProcessHostnameDnsLookup;
use App\Notifications\Service\OutboundUrlGuard;
use App\Notifications\Service\PhpCliProbe;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use InvalidArgumentException;
use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;
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

    public function testBlocksMetadataEvenWhenPrivateUrlsEnabled(): void
    {
        $guard = $this->guard(true);

        foreach (['http://169.254.169.254/latest/meta-data/', 'http://metadata.google.internal/', 'http://100.100.100.200/latest/meta-data/', 'http://2852039166/latest/meta-data/', 'http://[::ffff:169.254.169.254]/latest/meta-data/'] as $url) {
            try {
                $guard->assertSafeHttpUrl($url);
                self::fail('Expected metadata to stay blocked for '.$url);
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
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

    public function testFrankenPhpFallsBackToInProcessDns(): void
    {
        $guard = new OutboundUrlGuard(
            $this->ops(false),
            new HostnameDnsLookup(),
            new PhpCliProbe('', 'cli'),
        );

        $options = $guard->httpClientOptionsForUrl('https://example.com/webhook');
        self::assertSame('example.com', array_key_first($options['resolve']));
        self::assertNotFalse(filter_var($options['resolve']['example.com'], \FILTER_VALIDATE_IP));

        $lookup = new InProcessHostnameDnsLookup();
        self::assertNotSame([], $lookup->hostByNameL('example.com'));
        self::assertFalse($lookup->hostByNameL('nonexistent.invalid'));
        self::assertFalse($lookup->dnsGetRecord('nonexistent.invalid', \DNS_A));
    }

    public function testPhpCliProbeRejectsFrankenPhpAndMissingBinaries(): void
    {
        self::assertFalse((new PhpCliProbe('', 'cli'))->supportsDashR());
        self::assertFalse((new PhpCliProbe('/usr/local/bin/php', 'frankenphp'))->supportsDashR());
        self::assertFalse((new PhpCliProbe('/no/such/php', 'cli'))->supportsDashR());

        $franken = tempnam(sys_get_temp_dir(), 'frankenphp');
        self::assertNotFalse($franken);
        $named = \dirname($franken).'/frankenphp-probe';
        rename($franken, $named);
        try {
            self::assertFalse((new PhpCliProbe($named, 'cli'))->supportsDashR());
        } finally {
            unlink($named);
        }

        self::assertTrue((new PhpCliProbe(\PHP_BINARY, 'cli'))->supportsDashR());
    }

    private function ops(bool $allowPrivate): InstanceOpsDefaults
    {
        $settings = InstanceSettings::defaults()->setAllowPrivateUrls($allowPrivate);
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        return new InstanceOpsDefaults($repo);
    }

    private function guard(bool $allowPrivate): OutboundUrlGuard
    {
        return new OutboundUrlGuard($this->ops($allowPrivate));
    }
}
