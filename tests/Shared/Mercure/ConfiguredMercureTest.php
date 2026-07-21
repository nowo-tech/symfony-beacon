<?php

declare(strict_types=1);

namespace App\Tests\Shared\Mercure;

use App\Shared\Mercure\ConfiguredMercure;
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
        );

        self::assertTrue($mercure->isEnabled());
        self::assertSame('https://localhost/.well-known/mercure', $mercure->getPublicUrl());
        self::assertNotNull($mercure->createSubscriberToken(['/projects/x/issues']));
    }
}
