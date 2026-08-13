<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Dto\DashboardActivityFilters;
use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Service\AccessibleProjectFilter;
use App\Project\Service\AccessibleProjectsProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves dashboard Activity filters from the request and actor access.
 */
final readonly class DashboardActivityFilterResolver
{
    public function __construct(
        private AccessibleProjectsProvider $accessibleProjects,
    ) {
    }

    public function resolve(User $user, Request $request): DashboardActivityFilters
    {
        $accessible = $this->accessibleProjects->forUser($user);
        $project = AccessibleProjectFilter::resolve($accessible, $request->query->getString('project'));

        return new DashboardActivityFilters(
            accessibleProjects: $accessible,
            projectUuids: $project instanceof Project
                ? [$project->getUuid()]
                : array_map(static fn (Project $project): string => $project->getUuid(), $accessible),
            project: $project,
        );
    }
}
