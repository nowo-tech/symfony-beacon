<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Mercure;

use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;

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
}
