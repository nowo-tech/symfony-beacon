<?php

declare(strict_types=1);

namespace App\Shared\Navigation;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves which app section (and sidebar menu) the current request belongs to.
 */
final readonly class AppSectionResolver
{
    /** @var list<string> */
    private const array ADMINISTRATION_PREFIXES = [
        'admin_',
        'settings_',
        'nowo_dashboard_menu_',
        'nowo_breadcrumb_kit_',
    ];

    /** @var list<string> */
    private const array PREFERENCES_PREFIXES = [
        'account_',
    ];

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function current(): AppSection
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route');
        if (!\is_string($route) || '' === $route) {
            return AppSection::Dashboard;
        }

        foreach (self::PREFERENCES_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return AppSection::Preferences;
            }
        }

        foreach (self::ADMINISTRATION_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return AppSection::Administration;
            }
        }

        return AppSection::Dashboard;
    }
}
