<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings;

use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use App\Shared\Appearance\SiteAppearanceProvider;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceConfigPortability;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class InstanceConfigPortabilitySecurityFlagsTest extends TestCase
{
    public function testImportDoesNotWeakenSecuritySensitiveFlags(): void
    {
        $settings = InstanceSettings::defaults();
        self::assertTrue($settings->isMetricsRequireToken());
        self::assertFalse($settings->isAllowPrivateUrls());
        self::assertFalse($settings->isAllowAnonymousResolve());

        $this->applyInstanceFlags($settings, [
            'ingest_reject_query_auth' => false, // obsolete key ignored
            'metrics_require_token' => false,
            'notifications_allow_private_urls' => true,
            'hooks_allow_anonymous_resolve' => true,
            'retention_days' => 14,
        ]);

        self::assertTrue($settings->isMetricsRequireToken());
        self::assertFalse($settings->isAllowPrivateUrls());
        self::assertFalse($settings->isAllowAnonymousResolve());
        self::assertSame(14, $settings->getRetentionDays());
    }

    public function testImportMayTightenSecuritySensitiveFlags(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setMetricsRequireToken(false);
        $settings->setAllowPrivateUrls(true);
        $settings->setAllowAnonymousResolve(true);

        $this->applyInstanceFlags($settings, [
            'metrics_require_token' => true,
            'notifications_allow_private_urls' => false,
            'hooks_allow_anonymous_resolve' => false,
        ]);

        self::assertTrue($settings->isMetricsRequireToken());
        self::assertFalse($settings->isAllowPrivateUrls());
        self::assertFalse($settings->isAllowAnonymousResolve());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyInstanceFlags(InstanceSettings $settings, array $data): void
    {
        $settingsRepo = $this->createMock(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);
        $settingsRepo->expects(self::once())->method('save')->with($settings);

        $portability = new ReflectionMethod(InstanceConfigPortability::class, '__construct')->getDeclaringClass()
            ->newInstanceWithoutConstructor();

        $ref = new ReflectionClass($portability);
        $ref->getProperty('appearanceRepository')->setValue($portability, $this->createStub(SiteAppearanceRepository::class));
        $ref->getProperty('instanceSettingsRepository')->setValue($portability, $settingsRepo);
        $ref->getProperty('appearanceProvider')->setValue(
            $portability,
            new ReflectionClass(SiteAppearanceProvider::class)->newInstanceWithoutConstructor(),
        );

        $method = new ReflectionMethod(InstanceConfigPortability::class, 'applyInstanceFlags');
        $method->invoke($portability, $data);
    }
}
