<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Mercure;

use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\Update;

final class ConfiguredMercureTest extends TestCase
{
    public function testDisabledWhenAdminFlagOff(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled(false);

        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $mercure = new ConfiguredMercure(
            $repo,
            'http://mercure/.well-known/mercure',
            'https://localhost/.well-known/mercure',
            '!ChangeThisMercureHubJWTSecretKey!',
            new MercureHubUrlGuard(),
        );

        self::assertFalse($mercure->isEnabled());
        self::assertNull($mercure->getPublicUrl());
        self::assertNull($mercure->createSubscriberToken(['/projects/x/issues']));
    }

    public function testEnabledUsesEnvFallbacksWhenFlagOn(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled(true);

        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $mercure = new ConfiguredMercure(
            $repo,
            'http://mercure/.well-known/mercure',
            'https://localhost/.well-known/mercure',
            '!ChangeThisMercureHubJWTSecretKey!',
            new MercureHubUrlGuard(),
        );

        self::assertTrue($mercure->isEnabled());
        self::assertSame('https://localhost/.well-known/mercure', $mercure->getPublicUrl());
        self::assertNotNull($mercure->createSubscriberToken(['/projects/x/issues']));
    }

    public function testFallsBackWhenDatabaseValuesAreUndecryptedCiphertext(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled(true);
        $cipher = 'MUIFAF1ene-U5Wd17tCqXXkkbzQFjYovX_aTv4DhkRuMMGHvQJwsex8xbphYtyxYRypbsVoaBg6VBl5CYm4OntYanYEv3tmoyqtQ4eVKLaxuJkBGWFjFNC9Jr46IveyM360xcHP3CZQaGl-Iofz-t5hsliBhs2Fa8MS87ikmeXI2Z-GITNWi8ajWxUo-UTBweXKat2ucQakpf_Rq51yivYXBzq-zyA==';
        $settings->setMercureUrl($cipher);
        $settings->setMercurePublicUrl($cipher);
        $settings->setMercureJwtSecret($cipher);

        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $mercure = new ConfiguredMercure(
            $repo,
            'http://mercure/.well-known/mercure',
            'https://localhost/.well-known/mercure',
            '!ChangeThisMercureHubJWTSecretKey!',
            new MercureHubUrlGuard(),
        );

        self::assertTrue($mercure->isEnabled());
        self::assertFalse($mercure->isUsingDatabaseUrl());
        self::assertFalse($mercure->isUsingDatabaseSecret());
        self::assertSame('https://localhost/.well-known/mercure', $mercure->getPublicUrl());
    }

    public function testCreateSubscriberTokenReturnsNullWhenSecretBecomesUnavailableAfterEnableCheck(): void
    {
        $settings = new class extends InstanceSettings {
            private int $secretCalls = 0;

            public function isMercureEnabled(): bool
            {
                return true;
            }

            public function getMercureUrl(): string
            {
                return 'http://mercure/.well-known/mercure';
            }

            public function getMercureJwtSecret(): string
            {
                return 0 === $this->secretCalls++ ? 'first-secret' : '';
            }
        };

        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $mercure = new ConfiguredMercure(
            $repo,
            '',
            '',
            '',
            new MercureHubUrlGuard(),
        );

        self::assertNull($mercure->createSubscriberToken(['/projects/x/issues']));
    }

    public function testPublishReturnsEarlyWhenDisabled(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled(false);

        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $mercure = new ConfiguredMercure(
            $repo,
            'http://mercure/.well-known/mercure',
            'https://localhost/.well-known/mercure',
            '!ChangeThisMercureHubJWTSecretKey!',
            new MercureHubUrlGuard(),
        );

        self::assertFalse($mercure->isEnabled());
        $mercure->publish(new Update('/projects/x/issues', '{"ok":true}'));
    }

    public function testPrivateHubReturnsCachedHubInstance(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled(true);
        $settings->setMercureUrl('http://mercure/.well-known/mercure');
        $settings->setMercureJwtSecret('cached-secret');

        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $mercure = new ConfiguredMercure(
            $repo,
            '',
            '',
            '',
            new MercureHubUrlGuard(),
        );

        $cachedHub = new ReflectionClass(Hub::class)->newInstanceWithoutConstructor();
        new ReflectionProperty($mercure, 'hub')->setValue($mercure, $cachedHub);

        self::assertSame($cachedHub, $this->invokePrivateMethod($mercure, 'hub'));
    }

    public function testPrivateHubThrowsWhenMercureIsNotConfigured(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled(true);
        $settings->setMercureUrl(null);
        $settings->setMercureJwtSecret(null);

        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $mercure = new ConfiguredMercure(
            $repo,
            '',
            '',
            '',
            new MercureHubUrlGuard(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mercure is not configured.');

        $this->invokePrivateMethod($mercure, 'hub');
    }

    private function invokePrivateMethod(object $object, string $method): mixed
    {
        $reflection = new ReflectionMethod($object, $method);

        return $reflection->invoke($object);
    }
}
