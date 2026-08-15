<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Identity\Entity\User;
use App\Notifications\Dto\DashboardAlertsFilters;
use App\Project\Service\DashboardProjectSelectionResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves dashboard Alerts filters from the request and actor access.
 */
final readonly class DashboardAlertsFilterResolver
{
    public function __construct(
        private DashboardProjectSelectionResolver $projectSelection,
    ) {
    }

    public function resolve(User $user, Request $request): DashboardAlertsFilters
    {
        $selection = $this->projectSelection->resolve($user, $request);

        return new DashboardAlertsFilters(
            accessibleProjects: $selection['accessible'],
            selectedProjects: $selection['selected'],
            project: $selection['project'],
        );
    }
}
