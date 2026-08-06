<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Appearance\AppearanceSettingsSection;
use App\Shared\Appearance\AppearanceSettingsSubtab;
use PHPUnit\Framework\TestCase;

final class AppearanceSettingsSectionTest extends TestCase
{
    public function testSectionHelpers(): void
    {
        self::assertSame('appearance.tab.themes', AppearanceSettingsSection::Themes->tabLabelKey());
        self::assertSame('appearance.tab.brand_help', AppearanceSettingsSection::Brand->helpKey());
        self::assertSame([], AppearanceSettingsSection::Layout->subtabs());
        self::assertNull(AppearanceSettingsSection::Layout->defaultSubtab());

        self::assertSame(
            [
                AppearanceSettingsSubtab::Accents,
                AppearanceSettingsSubtab::Status,
                AppearanceSettingsSubtab::Surfaces,
            ],
            AppearanceSettingsSection::Colors->subtabs(),
        );
        self::assertSame(AppearanceSettingsSubtab::Accents, AppearanceSettingsSection::Colors->defaultSubtab());
        self::assertSame('themes|brand|layout|colors', AppearanceSettingsSection::routeRequirement());
    }

    public function testSubtabHelpers(): void
    {
        self::assertSame('appearance.subtab.status', AppearanceSettingsSubtab::Status->tabLabelKey());
        self::assertSame('accents|status|surfaces', AppearanceSettingsSubtab::routeRequirement());
    }
}
