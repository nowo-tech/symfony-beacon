<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Appearance\AppearanceBorderStyles;
use App\Shared\Appearance\Entity\SiteAppearance;
use PHPUnit\Framework\TestCase;

final class AppearanceBorderStylesTest extends TestCase
{
    public function testStrongUsesHigherMixThanSubtle(): void
    {
        $subtle = AppearanceBorderStyles::tokens(SiteAppearance::BORDER_SUBTLE);
        $strong = AppearanceBorderStyles::tokens(SiteAppearance::BORDER_STRONG);

        self::assertGreaterThan($subtle['sandMixLight'], $strong['sandMixLight']);
        self::assertGreaterThan($subtle['sandMixDark'], $strong['sandMixDark']);
        self::assertSame('1.5px', $strong['width']);
        self::assertSame('1px', $subtle['width']);
    }

    public function testDefaultTokensAndValidation(): void
    {
        $default = AppearanceBorderStyles::tokens('unknown');
        self::assertSame(14, $default['sandMixLight']);
        self::assertSame(16, $default['sandMixDark']);
        self::assertSame('1px', $default['width']);

        self::assertTrue(AppearanceBorderStyles::isValid(SiteAppearance::BORDER_SUBTLE));
        self::assertFalse(AppearanceBorderStyles::isValid('nope'));
    }
}
