<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Appearance\AppearanceCornerStyles;
use App\Shared\Appearance\Entity\SiteAppearance;
use PHPUnit\Framework\TestCase;

final class AppearanceCornerStylesTest extends TestCase
{
    public function testRoundedUsesLargerCardThanControl(): void
    {
        $radii = AppearanceCornerStyles::radii(SiteAppearance::CORNER_ROUNDED);

        self::assertSame('1.25rem', $radii['card']);
        self::assertSame('0.5rem', $radii['control']);
        self::assertGreaterThan((float) $radii['control'], (float) $radii['card']);
    }

    public function testSharpIsNearlySquare(): void
    {
        $radii = AppearanceCornerStyles::radii(SiteAppearance::CORNER_SHARP);

        self::assertSame('0.125rem', $radii['card']);
        self::assertSame('0.0625rem', $radii['control']);
    }
}
