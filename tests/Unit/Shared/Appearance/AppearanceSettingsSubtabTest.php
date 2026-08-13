<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Appearance;

use App\Shared\Appearance\AppearanceSettingsSubtab;
use PHPUnit\Framework\TestCase;

final class AppearanceSettingsSubtabTest extends TestCase
{
    public function testLabelsAndRouteRequirement(): void
    {
        self::assertSame('appearance.subtab.accents', AppearanceSettingsSubtab::Accents->tabLabelKey());
        self::assertSame('appearance.subtab.status', AppearanceSettingsSubtab::Status->tabLabelKey());
        self::assertSame('appearance.subtab.surfaces', AppearanceSettingsSubtab::Surfaces->tabLabelKey());
        self::assertSame('accents|status|surfaces', AppearanceSettingsSubtab::routeRequirement());
    }
}
