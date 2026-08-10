<?php

declare(strict_types=1);

namespace App\Shared\Menu;

use Nowo\DashboardMenuBundle\Service\AbstractRoutePrefixMenuCurrentMatcher;

/**
 * Marks the dashboard "Projects" sidebar item current on project product surfaces.
 *
 * The menu link targets {@code dashboard_home}; child pages use {@code project_*},
 * {@code issue_*}, {@code performance_*}, etc. under {@code /projects/{uuid}/…}.
 */
final class DashboardMenuCurrentMatcher extends AbstractRoutePrefixMenuCurrentMatcher
{
    /** @var array<string, list<string>> */
    private const array ROUTE_PREFIXES = [
        'dashboard_home' => [
            'dashboard_home',
            'project_',
            'issue_',
            'event_',
            'performance_',
            'analytics_',
        ],
    ];

    /** @return array<string, list<string>> */
    protected function routePrefixes(): array
    {
        return self::ROUTE_PREFIXES;
    }
}
