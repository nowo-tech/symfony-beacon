<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Identity\Entity\User;
use App\Notifications\Dto\DashboardAlertsFilters;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectFilter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves dashboard Alerts filters from the request and actor access.
 */
final readonly class DashboardAlertsFilterResolver
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {
    }

    public function resolve(User $user, Request $request): DashboardAlertsFilters
    {
        $accessible = $this->projectRepository->findAccessibleByUser($user);
        $project = AccessibleProjectFilter::resolve($accessible, $request->query->getString('project'));

        return new DashboardAlertsFilters(
            accessibleProjects: $accessible,
            selectedProjects: $project instanceof Project ? [$project] : $accessible,
            project: $project,
        );
    }
}
