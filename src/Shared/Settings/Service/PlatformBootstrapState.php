<?php

declare(strict_types=1);

namespace App\Shared\Settings\Service;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;

/**
 * Detects whether platform catalogs (menus, breadcrumbs, cookie consent) still need seeding.
 */
final readonly class PlatformBootstrapState
{
    /** @var list<string> */
    private const REQUIRED_MENU_CODES = ['dashboard', 'preferences', 'administration'];

    public function __construct(
        private MenuRepository $menuRepository,
        private BreadcrumbCollectionRepository $breadcrumbCollectionRepository,
        private CookieConsentConfigRepository $cookieConsentConfigRepository,
    ) {
    }

    public function needsPlatformSeed(): bool
    {
        return !$this->hasRequiredMenus()
            || !$this->hasDefaultBreadcrumbs()
            || !$this->hasDefaultCookieConsent();
    }

    public function hasRequiredMenus(): bool
    {
        foreach (self::REQUIRED_MENU_CODES as $code) {
            $menu = $this->menuRepository->findOneByCodeAndContext($code, null);
            if (!$menu instanceof Menu || $menu->getItems()->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    public function hasDefaultBreadcrumbs(): bool
    {
        $collection = $this->breadcrumbCollectionRepository->findOneByCodeAndContextKey('default', '');
        if (!$collection instanceof BreadcrumbCollection) {
            return false;
        }

        return !$collection->getItems()->isEmpty();
    }

    public function hasDefaultCookieConsent(): bool
    {
        return $this->cookieConsentConfigRepository->findDefaultEnabled() instanceof CookieConsentConfig;
    }
}
