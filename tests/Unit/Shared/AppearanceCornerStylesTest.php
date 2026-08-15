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

    public function testDefaultRadiiAndValidation(): void
    {
        $radii = AppearanceCornerStyles::radii('unknown');
        self::assertSame('0.75rem', $radii['card']);
        self::assertSame('0.375rem', $radii['control']);
        self::assertTrue(AppearanceCornerStyles::isValid(SiteAppearance::CORNER_ROUNDED));
        self::assertFalse(AppearanceCornerStyles::isValid('nope'));
    }
}
