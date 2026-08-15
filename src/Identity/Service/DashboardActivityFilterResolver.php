<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Dto\DashboardActivityFilters;
use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Service\DashboardProjectSelectionResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves dashboard Activity filters from the request and actor access.
 */
final readonly class DashboardActivityFilterResolver
{
    public function __construct(
        private DashboardProjectSelectionResolver $projectSelection,
    ) {
    }

    public function resolve(User $user, Request $request): DashboardActivityFilters
    {
        $selection = $this->projectSelection->resolve($user, $request);
        $project = $selection['project'];

        return new DashboardActivityFilters(
            accessibleProjects: $selection['accessible'],
            projectUuids: $project instanceof Project
                ? [$project->getUuid()]
                : array_map(static fn (Project $p): string => $p->getUuid(), $selection['accessible']),
            project: $project,
        );
    }
}
