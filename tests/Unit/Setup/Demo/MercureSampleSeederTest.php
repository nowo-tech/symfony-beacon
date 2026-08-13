<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup\Demo;

use App\Setup\Demo\MercureSampleSeeder;
use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;

final class MercureSampleSeederTest extends TestCase
{
    public function testSeedDefaultsEnablesAndFillsEmptyFieldsFromEnv(): void
    {
        $settings = InstanceSettings::defaults();
        $saved = 0;
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);
        $repo->method('save')->willReturnCallback(static function () use (&$saved): void {
            ++$saved;
        });

        $seeder = new MercureSampleSeeder(
            $repo,
            new ConfiguredMercure($repo, 'http://mercure.test/.well-known/mercure', 'https://public.test', 'jwt-secret-value', new MercureHubUrlGuard()),
            'http://mercure.test/.well-known/mercure',
            'https://public.test',
            'jwt-secret-value',
        );

        self::assertTrue($seeder->seedDefaults());
        self::assertTrue($settings->isMercureEnabled());
        self::assertSame('http://mercure.test/.well-known/mercure', $settings->getMercureUrl());
        self::assertSame('https://public.test', $settings->getMercurePublicUrl());
        self::assertTrue($settings->hasMercureJwtSecret());
        self::assertSame(1, $saved);

        self::assertFalse($seeder->seedDefaults());
    }
}
