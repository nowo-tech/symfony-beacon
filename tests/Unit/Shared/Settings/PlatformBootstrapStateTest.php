<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings;

use App\Shared\Settings\Service\PlatformBootstrapState;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use PHPUnit\Framework\TestCase;

final class PlatformBootstrapStateTest extends TestCase
{
    public function testNeedsSeedWhenMenusMissing(): void
    {
        $menus = $this->createStub(MenuRepository::class);
        $menus->method('findOneByCodeAndContext')->willReturn(null);

        $state = new PlatformBootstrapState(
            $menus,
            $this->createStub(BreadcrumbCollectionRepository::class),
            $this->createStub(CookieConsentConfigRepository::class),
        );

        self::assertTrue($state->needsPlatformSeed());
        self::assertFalse($state->hasRequiredMenus());
    }

    public function testNeedsSeedWhenBreadcrumbsEmpty(): void
    {
        $state = new PlatformBootstrapState(
            $this->menusWithRequiredCodes(),
            $this->emptyBreadcrumbs(),
            $this->cookieConsentPresent(),
        );

        self::assertTrue($state->needsPlatformSeed());
        self::assertTrue($state->hasRequiredMenus());
        self::assertFalse($state->hasDefaultBreadcrumbs());
        self::assertTrue($state->hasDefaultCookieConsent());
    }

    public function testNeedsSeedWhenBreadcrumbCollectionIsMissing(): void
    {
        $breadcrumbs = $this->createStub(BreadcrumbCollectionRepository::class);
        $breadcrumbs->method('findOneByCodeAndContextKey')->willReturn(null);

        $state = new PlatformBootstrapState(
            $this->menusWithRequiredCodes(),
            $breadcrumbs,
            $this->cookieConsentPresent(),
        );

        self::assertFalse($state->hasDefaultBreadcrumbs());
    }

    public function testReadyWhenAllCatalogsPresent(): void
    {
        $collection = new BreadcrumbCollection();
        $collection->addItem(new BreadcrumbItem());

        $breadcrumbs = $this->createStub(BreadcrumbCollectionRepository::class);
        $breadcrumbs->method('findOneByCodeAndContextKey')->willReturnCallback(
            static fn (string $code, string $contextKey): ?BreadcrumbCollection => 'default' === $code && '' === $contextKey ? $collection : null,
        );

        $state = new PlatformBootstrapState(
            $this->menusWithRequiredCodes(),
            $breadcrumbs,
            $this->cookieConsentPresent(),
        );

        self::assertFalse($state->needsPlatformSeed());
        self::assertTrue($state->hasRequiredMenus());
        self::assertTrue($state->hasDefaultBreadcrumbs());
        self::assertTrue($state->hasDefaultCookieConsent());
    }

    private function menusWithRequiredCodes(): MenuRepository
    {
        $menus = $this->createStub(MenuRepository::class);
        $menus->method('findOneByCodeAndContext')->willReturnCallback(
            static function (string $code): Menu {
                $menu = new Menu();
                $menu->setCode($code);
                $menu->addItem(new MenuItem());

                return $menu;
            },
        );

        return $menus;
    }

    private function emptyBreadcrumbs(): BreadcrumbCollectionRepository
    {
        $breadcrumbs = $this->createStub(BreadcrumbCollectionRepository::class);
        $breadcrumbs->method('findOneByCodeAndContextKey')->willReturn(new BreadcrumbCollection());

        return $breadcrumbs;
    }

    private function cookieConsentPresent(): CookieConsentConfigRepository
    {
        $cookies = $this->createStub(CookieConsentConfigRepository::class);
        $cookies->method('findDefaultEnabled')->willReturn(new CookieConsentConfig());

        return $cookies;
    }
}
