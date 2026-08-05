<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Appearance\AppearanceThemePresets;
use App\Shared\Appearance\Entity\SiteAppearance;
use PHPUnit\Framework\TestCase;

final class AppearanceThemePresetsTest extends TestCase
{
    public function testApplyOverwritesPaletteAndSetsThemeId(): void
    {
        $appearance = SiteAppearance::defaults();
        $appearance
            ->setAccentColor('#abcdef')
            ->setPaperColor('#111111')
            ->setThemeId(AppearanceThemePresets::CUSTOM);

        self::assertTrue(AppearanceThemePresets::apply($appearance, 'midnight'));
        self::assertSame('midnight', $appearance->getThemeId());
        self::assertSame('#1d4ed8', $appearance->getAccentColor());
        self::assertSame('#070b16', $appearance->getPaperColorDark());
        self::assertSame('midnight', AppearanceThemePresets::matchId($appearance));
    }

    public function testMatchIdReturnsCustomWhenColorsDiverge(): void
    {
        $appearance = SiteAppearance::defaults();
        AppearanceThemePresets::apply($appearance, AppearanceThemePresets::BEACON);
        $appearance->setAccentColor('#abcdef');

        self::assertSame(AppearanceThemePresets::CUSTOM, AppearanceThemePresets::matchId($appearance));
    }

    public function testLightAndDarkGroupsCoverCatalog(): void
    {
        $light = AppearanceThemePresets::byMode('light');
        $dark = AppearanceThemePresets::byMode('dark');

        self::assertNotEmpty($light);
        self::assertNotEmpty($dark);
        self::assertSame(\count(AppearanceThemePresets::all()), \count($light) + \count($dark));
    }
}
