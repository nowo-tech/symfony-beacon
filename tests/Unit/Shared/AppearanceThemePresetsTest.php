<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Appearance\AppearanceThemePresets;
use App\Shared\Appearance\Entity\SiteAppearance;
use PHPUnit\Framework\TestCase;

final class AppearanceThemePresetsTest extends TestCase
{
    public function testApplyDarkThemeOnlyTouchesDarkFields(): void
    {
        $appearance = SiteAppearance::defaults();
        $appearance
            ->setAccentColor('#abcdef')
            ->setPaperColor('#111111')
            ->setThemeId(AppearanceThemePresets::CUSTOM);

        self::assertTrue(AppearanceThemePresets::apply($appearance, 'midnight'));
        self::assertSame(AppearanceThemePresets::CUSTOM, $appearance->getThemeId());
        self::assertSame('midnight', $appearance->getThemeIdDark());
        self::assertSame('#abcdef', $appearance->getAccentColor());
        self::assertSame('#111111', $appearance->getPaperColor());
        self::assertSame('#60a5fa', $appearance->getAccentColorDark());
        self::assertSame('#070b16', $appearance->getPaperColorDark());
        self::assertSame('midnight', AppearanceThemePresets::matchDarkId($appearance));
        self::assertSame(AppearanceThemePresets::CUSTOM, AppearanceThemePresets::matchLightId($appearance));
    }

    public function testApplyLightThemeOnlyTouchesLightFields(): void
    {
        $appearance = SiteAppearance::defaults();
        AppearanceThemePresets::apply($appearance, 'midnight');
        AppearanceThemePresets::apply($appearance, 'ocean');

        self::assertSame('ocean', $appearance->getThemeId());
        self::assertSame('midnight', $appearance->getThemeIdDark());
        self::assertSame('#0e7490', $appearance->getAccentColor());
        self::assertSame('#60a5fa', $appearance->getAccentColorDark());
        self::assertSame('ocean', AppearanceThemePresets::matchLightId($appearance));
        self::assertSame('midnight', AppearanceThemePresets::matchDarkId($appearance));
    }

    public function testMatchLightIdReturnsCustomWhenLightColorsDiverge(): void
    {
        $appearance = SiteAppearance::defaults();
        AppearanceThemePresets::apply($appearance, AppearanceThemePresets::BEACON);
        $appearance->setAccentColor('#abcdef');

        self::assertSame(AppearanceThemePresets::CUSTOM, AppearanceThemePresets::matchLightId($appearance));
    }

    public function testLightAndDarkGroupsCoverCatalog(): void
    {
        $light = AppearanceThemePresets::byMode('light');
        $dark = AppearanceThemePresets::byMode('dark');

        self::assertNotEmpty($light);
        self::assertNotEmpty($dark);
        self::assertSame(\count(AppearanceThemePresets::all()), \count($light) + \count($dark));
    }

    public function testMatchIdPrefersLightAndFallsBackToDark(): void
    {
        $light = SiteAppearance::defaults();
        AppearanceThemePresets::apply($light, 'ocean');
        self::assertSame('ocean', AppearanceThemePresets::matchId($light));

        $dark = SiteAppearance::defaults();
        $dark->setAccentColor('#123456');
        AppearanceThemePresets::apply($dark, 'midnight');
        self::assertSame('midnight', AppearanceThemePresets::matchId($dark));
    }

    public function testMatchForModeDelegatesToLightOrDarkCatalog(): void
    {
        $appearance = SiteAppearance::defaults();
        AppearanceThemePresets::apply($appearance, 'ocean');
        AppearanceThemePresets::apply($appearance, 'midnight');

        self::assertSame('midnight', AppearanceThemePresets::matchForMode($appearance, 'dark'));
        self::assertSame('ocean', AppearanceThemePresets::matchForMode($appearance, 'light'));
    }
}
