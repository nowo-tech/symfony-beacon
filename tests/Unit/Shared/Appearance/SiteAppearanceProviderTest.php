<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Appearance;

use App\Shared\Appearance\Entity\SiteAppearance;
use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use App\Shared\Appearance\SiteAppearanceProvider;
use PHPUnit\Framework\TestCase;

final class SiteAppearanceProviderTest extends TestCase
{
    public function testCachesGetAndRefreshClearsCache(): void
    {
        $calls = 0;
        $appearance = SiteAppearance::defaults();
        $repo = $this->createStub(SiteAppearanceRepository::class);
        $repo->method('getOrCreate')->willReturnCallback(static function () use (&$calls, $appearance): SiteAppearance {
            ++$calls;

            return $appearance;
        });
        $provider = new SiteAppearanceProvider($repo);

        self::assertSame($appearance, $provider->get());
        self::assertSame($appearance, $provider->get());
        self::assertSame(1, $calls);

        $provider->reset();
        self::assertSame($appearance, $provider->get());
        self::assertSame(2, $calls);

        self::assertSame($appearance, $provider->refresh());
        self::assertSame(3, $calls);
    }

    public function testDelegatesBrandLayoutAndColorGetters(): void
    {
        $appearance = SiteAppearance::defaults();
        $appearance->setBrandName('Beacon');
        $appearance->setBrandEyebrow('Ops');
        $appearance->setFooterFixed(true);
        $appearance->setCornerStyle(SiteAppearance::CORNER_SHARP);
        $appearance->setBorderStrength(SiteAppearance::BORDER_STRONG);
        $appearance->setAccentColor('#112233');
        $appearance->setAccentDeepColor('#445566');
        $appearance->setAccentColorDark('#778899');
        $appearance->setAccentDeepColorDark('#aabbcc');
        $appearance->setDangerColor('#ff0000');
        $appearance->setDangerColorDark('#ffaaaa');
        $appearance->setWarnColor('#ffaa00');
        $appearance->setWarnColorDark('#ffcc66');
        $appearance->setPaperColor('#ffffff');
        $appearance->setPaperColorDark('#000000');
        $appearance->setInkColor('#010203');
        $appearance->setInkColorDark('#fefefe');
        $appearance->setSurfaceColor('#eeeeee');
        $appearance->setSurfaceColorDark('#111111');

        $repo = $this->createStub(SiteAppearanceRepository::class);
        $repo->method('getOrCreate')->willReturn($appearance);
        $provider = new SiteAppearanceProvider($repo);

        self::assertSame('Beacon', $provider->getBrandName());
        self::assertSame('Ops', $provider->getBrandEyebrow());
        self::assertTrue($provider->isFooterFixed());
        self::assertSame(SiteAppearance::CORNER_SHARP, $provider->getCornerStyle());
        self::assertSame(SiteAppearance::BORDER_STRONG, $provider->getBorderStrength());
        self::assertSame('#112233', $provider->getAccentColor());
        self::assertSame('#445566', $provider->getAccentDeepColor());
        self::assertSame('#778899', $provider->getAccentColorDark());
        self::assertSame('#aabbcc', $provider->getAccentDeepColorDark());
        self::assertSame('#ff0000', $provider->getDangerColor());
        self::assertSame('#ffaaaa', $provider->getDangerColorDark());
        self::assertSame('#ffaa00', $provider->getWarnColor());
        self::assertSame('#ffcc66', $provider->getWarnColorDark());
        self::assertSame('#ffffff', $provider->getPaperColor());
        self::assertSame('#000000', $provider->getPaperColorDark());
        self::assertSame('#010203', $provider->getInkColor());
        self::assertSame('#fefefe', $provider->getInkColorDark());
        self::assertSame('#eeeeee', $provider->getSurfaceColor());
        self::assertSame('#111111', $provider->getSurfaceColorDark());
        self::assertNotSame('', $provider->getThemeId());
        self::assertNotSame('', $provider->getThemeIdDark());
    }

    public function testCssOverridesIncludeTokensAndHexFallback(): void
    {
        $appearance = SiteAppearance::defaults();
        $appearance->setInkColor('not-a-hex');
        $repo = $this->createStub(SiteAppearanceRepository::class);
        $repo->method('getOrCreate')->willReturn($appearance);
        $provider = new SiteAppearanceProvider($repo);

        $css = $provider->getCssOverrides();
        self::assertStringContainsString('--beacon-moss:', $css);
        self::assertStringContainsString('[data-theme="dark"]', $css);
        self::assertStringContainsString('--beacon-shadow: 15 28 24;', $css);

        $appearance->setInkColor('#0f1c18');
        $provider->refresh();
        self::assertStringContainsString('--beacon-shadow: 15 28 24;', $provider->getCssOverrides());
    }
}
