<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use Symfony\Component\HttpFoundation\Request;

/**
 * Shared dashboard project filter resolution (accessible list + optional UUID selection).
 */
final readonly class DashboardProjectSelectionResolver
{
    public function __construct(
        private AccessibleProjectsProvider $accessibleProjects,
    ) {
    }

    /**
     * @return array{
     *     accessible: list<Project>,
     *     project: ?Project,
     *     selected: list<Project>
     * }
     */
    public function resolve(User $user, Request $request): array
    {
        $accessible = $this->accessibleProjects->forUser($user);
        $project = AccessibleProjectFilter::resolve($accessible, $request->query->getString('project'));

        return [
            'accessible' => $accessible,
            'project' => $project,
            'selected' => $project instanceof Project ? [$project] : $accessible,
        ];
    }
}
