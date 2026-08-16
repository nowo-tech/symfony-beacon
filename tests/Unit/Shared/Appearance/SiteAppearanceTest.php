<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Appearance;

use App\Identity\Entity\User;
use App\Shared\Appearance\Entity\SiteAppearance;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SiteAppearanceTest extends TestCase
{
    public function testDefaultsNormalizeAndResetState(): void
    {
        $appearance = SiteAppearance::defaults();
        $user = new User();

        self::assertSame(1, $appearance->getId());
        self::assertSame(SiteAppearance::DEFAULT_BRAND_NAME, $appearance->getBrandName());
        self::assertSame(SiteAppearance::DEFAULT_BRAND_EYEBROW, $appearance->getBrandEyebrow());
        self::assertSame('beacon', $appearance->getThemeId());
        self::assertSame('custom', $appearance->getThemeIdDark());
        self::assertFalse($appearance->isFooterFixed());
        self::assertSame(SiteAppearance::CORNER_SOFT, $appearance->getCornerStyle());
        self::assertSame(SiteAppearance::BORDER_MEDIUM, $appearance->getBorderStrength());

        $appearance
            ->setBrandName('  Beacon Ops  ')
            ->setBrandEyebrow('  Alerts  ')
            ->setAccentColor(' #ABCDEF ')
            ->setAccentDeepColor(' #123456 ')
            ->setAccentColorDark(' #FEDCBA ')
            ->setAccentDeepColorDark(' #654321 ')
            ->setDangerColor(' #AA0000 ')
            ->setDangerColorDark(' #BB1111 ')
            ->setWarnColor(' #CC2200 ')
            ->setWarnColorDark(' #DD3300 ')
            ->setPaperColor(' #EEEFFF ')
            ->setPaperColorDark(' #111222 ')
            ->setInkColor(' #333444 ')
            ->setInkColorDark(' #FAFAFA ')
            ->setSurfaceColor(' #ABC123 ')
            ->setSurfaceColorDark(' #321CBA ')
            ->setThemeId('  AURORA ')
            ->setThemeIdDark('   ')
            ->setFooterFixed(true)
            ->setCornerStyle(' rounded ')
            ->setBorderStrength(' strong ');
        $appearance->setCreatedBy(new stdClass());
        $appearance->setUpdatedBy($user);

        self::assertSame('Beacon Ops', $appearance->getBrandName());
        self::assertSame('Alerts', $appearance->getBrandEyebrow());
        self::assertSame('#abcdef', $appearance->getAccentColor());
        self::assertSame('#123456', $appearance->getAccentDeepColor());
        self::assertSame('#fedcba', $appearance->getAccentColorDark());
        self::assertSame('#654321', $appearance->getAccentDeepColorDark());
        self::assertSame('#aa0000', $appearance->getDangerColor());
        self::assertSame('#bb1111', $appearance->getDangerColorDark());
        self::assertSame('#cc2200', $appearance->getWarnColor());
        self::assertSame('#dd3300', $appearance->getWarnColorDark());
        self::assertSame('#eeefff', $appearance->getPaperColor());
        self::assertSame('#111222', $appearance->getPaperColorDark());
        self::assertSame('#333444', $appearance->getInkColor());
        self::assertSame('#fafafa', $appearance->getInkColorDark());
        self::assertSame('#abc123', $appearance->getSurfaceColor());
        self::assertSame('#321cba', $appearance->getSurfaceColorDark());
        self::assertSame('aurora', $appearance->getThemeId());
        self::assertSame('custom', $appearance->getThemeIdDark());
        self::assertTrue($appearance->isFooterFixed());
        self::assertSame(SiteAppearance::CORNER_ROUNDED, $appearance->getCornerStyle());
        self::assertSame(SiteAppearance::BORDER_STRONG, $appearance->getBorderStrength());
        self::assertNull($appearance->getCreatedBy());
        self::assertSame($user, $appearance->getUpdatedBy());

        $appearance
            ->setCornerStyle('not-valid')
            ->setBorderStrength('not-valid');
        $appearance->setCreatedBy($user);
        $appearance->setUpdatedBy(new stdClass());
        $appearance->resetToDefaults();

        self::assertSame(SiteAppearance::DEFAULT_BRAND_NAME, $appearance->getBrandName());
        self::assertSame(SiteAppearance::DEFAULT_BRAND_EYEBROW, $appearance->getBrandEyebrow());
        self::assertSame(SiteAppearance::DEFAULT_ACCENT, $appearance->getAccentColor());
        self::assertSame(SiteAppearance::DEFAULT_ACCENT_DEEP, $appearance->getAccentDeepColor());
        self::assertSame(SiteAppearance::DEFAULT_ACCENT_DARK, $appearance->getAccentColorDark());
        self::assertSame(SiteAppearance::DEFAULT_ACCENT_DEEP_DARK, $appearance->getAccentDeepColorDark());
        self::assertSame(SiteAppearance::DEFAULT_DANGER, $appearance->getDangerColor());
        self::assertSame(SiteAppearance::DEFAULT_DANGER_DARK, $appearance->getDangerColorDark());
        self::assertSame(SiteAppearance::DEFAULT_WARN, $appearance->getWarnColor());
        self::assertSame(SiteAppearance::DEFAULT_WARN_DARK, $appearance->getWarnColorDark());
        self::assertSame(SiteAppearance::DEFAULT_PAPER, $appearance->getPaperColor());
        self::assertSame(SiteAppearance::DEFAULT_PAPER_DARK, $appearance->getPaperColorDark());
        self::assertSame(SiteAppearance::DEFAULT_INK, $appearance->getInkColor());
        self::assertSame(SiteAppearance::DEFAULT_INK_DARK, $appearance->getInkColorDark());
        self::assertSame(SiteAppearance::DEFAULT_SURFACE, $appearance->getSurfaceColor());
        self::assertSame(SiteAppearance::DEFAULT_SURFACE_DARK, $appearance->getSurfaceColorDark());
        self::assertSame('beacon', $appearance->getThemeId());
        self::assertSame('custom', $appearance->getThemeIdDark());
        self::assertFalse($appearance->isFooterFixed());
        self::assertSame(SiteAppearance::CORNER_SOFT, $appearance->getCornerStyle());
        self::assertSame(SiteAppearance::BORDER_MEDIUM, $appearance->getBorderStrength());
        self::assertSame($user, $appearance->getCreatedBy());
        self::assertNull($appearance->getUpdatedBy());
    }
}
