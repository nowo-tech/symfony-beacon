<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings;

use App\Shared\Appearance\Entity\SiteAppearance;
use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use App\Shared\Appearance\SiteAppearanceProvider;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceConfigPortability;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InstanceConfigPortabilityExportImportTest extends TestCase
{
    public function testExportIncludesSchemaAndAllowlistedFields(): void
    {
        $appearance = SiteAppearance::defaults();
        $appearance->setBrandName('Beacon Lab');
        $settings = InstanceSettings::defaults();
        $settings->setRetentionDays(30);
        $settings->setMercureEnabled(true);

        $portability = $this->portability($appearance, $settings);
        $payload = $portability->export();

        self::assertSame(InstanceConfigPortability::SCHEMA, $payload['schema']);
        self::assertSame(InstanceConfigPortability::VERSION, $payload['version']);
        self::assertSame('Beacon Lab', $payload['appearance']['brand_name']);
        self::assertTrue($payload['instance']['mercure_enabled']);
        self::assertSame(30, $payload['instance']['retention_days']);
        self::assertArrayNotHasKey('mailer_dsn', $payload['instance']);
        self::assertArrayNotHasKey('metrics_token', $payload['instance']);
    }

    public function testImportAppliesAppearanceAndInstanceAndRejectsForbiddenOrEmpty(): void
    {
        $appearance = SiteAppearance::defaults();
        $settings = InstanceSettings::defaults();
        $savedAppearance = 0;
        $savedSettings = 0;

        $appearanceRepo = $this->createStub(SiteAppearanceRepository::class);
        $appearanceRepo->method('getOrCreate')->willReturn($appearance);
        $appearanceRepo->method('save')->willReturnCallback(static function () use (&$savedAppearance): void {
            ++$savedAppearance;
        });
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);
        $settingsRepo->method('save')->willReturnCallback(static function () use (&$savedSettings): void {
            ++$savedSettings;
        });
        $provider = new SiteAppearanceProvider($appearanceRepo);
        $portability = new InstanceConfigPortability($appearanceRepo, $settingsRepo, $provider);

        $applied = $portability->import([
            'schema' => InstanceConfigPortability::SCHEMA,
            'version' => InstanceConfigPortability::VERSION,
            'appearance' => [
                'brand_name' => 'Imported',
                'brand_eyebrow' => 'Ops',
                'footer_fixed' => '1',
                'accent_color' => '#abcdef',
            ],
            'instance' => [
                'mercure_enabled' => true,
                'setup_completed' => true,
                'inbound_email_enabled' => true,
                'inbound_email_mail_domain' => 'inbound.example',
                'retention_days' => 7,
                'envelope_max_bytes' => 1_000_000,
            ],
        ]);

        self::assertSame(['appearance', 'instance'], $applied);
        self::assertSame('Imported', $appearance->getBrandName());
        self::assertSame('Ops', $appearance->getBrandEyebrow());
        self::assertTrue($appearance->isFooterFixed());
        self::assertSame('#abcdef', $appearance->getAccentColor());
        self::assertTrue($settings->isMercureEnabled());
        self::assertTrue($settings->isSetupCompleted());
        self::assertTrue($settings->isInboundEmailEnabled());
        self::assertSame('inbound.example', $settings->getInboundMailDomain());
        self::assertSame(7, $settings->getRetentionDays());
        self::assertSame(1_000_000, $settings->getEnvelopeMaxBytes());
        self::assertSame(1, $savedAppearance);
        self::assertSame(1, $savedSettings);

        try {
            $portability->import([
                'schema' => InstanceConfigPortability::SCHEMA,
                'version' => InstanceConfigPortability::VERSION,
                'instance' => ['mailer_dsn' => 'smtp://x'],
            ]);
            self::fail('expected forbidden');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('forbidden_key', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $portability->import([
            'schema' => InstanceConfigPortability::SCHEMA,
            'version' => InstanceConfigPortability::VERSION,
        ]);
    }

    public function testImportThemeShortcutSkipsIndividualColors(): void
    {
        $appearance = SiteAppearance::defaults();
        $appearanceRepo = $this->createStub(SiteAppearanceRepository::class);
        $appearanceRepo->method('getOrCreate')->willReturn($appearance);
        $appearanceRepo->method('save');
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $portability = new InstanceConfigPortability(
            $appearanceRepo,
            $settingsRepo,
            new SiteAppearanceProvider($appearanceRepo),
        );

        $portability->import([
            'schema' => InstanceConfigPortability::SCHEMA,
            'version' => InstanceConfigPortability::VERSION,
            'appearance' => [
                'theme_id' => 'beacon',
                'accent_color' => '#000000', // ignored when theme applied
            ],
        ]);

        self::assertNotSame('#000000', $appearance->getAccentColor());
    }

    private function portability(SiteAppearance $appearance, InstanceSettings $settings): InstanceConfigPortability
    {
        $appearanceRepo = $this->createStub(SiteAppearanceRepository::class);
        $appearanceRepo->method('getOrCreate')->willReturn($appearance);
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);

        return new InstanceConfigPortability(
            $appearanceRepo,
            $settingsRepo,
            new SiteAppearanceProvider($appearanceRepo),
        );
    }
}
