<?php

declare(strict_types=1);

namespace App\Shared\Menu;

/**
 * Marks preferences sidebar links current for whole account areas (exact path match is too narrow).
 *
 * Preserves {@see \Nowo\DashboardMenuBundle\Service\CurrentRouteTreeDecorator} matches and
 * ORs account route-prefix rules so related pages keep the same nav item highlighted.
 */
final class PreferencesMenuCurrentMarker extends RoutePrefixMenuCurrentMarker
{
    /** @var array<string, list<string>> */
    private const array ROUTE_PREFIXES = [
        'account_profile' => ['account_profile', 'account_projects', 'account_groups', 'account_privacy'],
        'account_security' => ['account_security'],
        'account_display' => ['account_display'],
    ];

    /** @return array<string, list<string>> */
    protected function routePrefixes(): array
    {
        return self::ROUTE_PREFIXES;
    }
}
