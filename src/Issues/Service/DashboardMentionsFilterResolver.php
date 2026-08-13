<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Dto\DashboardMentionsFilters;
use App\Project\Entity\Project;
use App\Project\Service\AccessibleProjectFilter;
use App\Project\Service\AccessibleProjectsProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves dashboard Mentions filters from the request and actor access.
 */
final readonly class DashboardMentionsFilterResolver
{
    public function __construct(
        private AccessibleProjectsProvider $accessibleProjects,
    ) {
    }

    public function resolve(User $user, Request $request): DashboardMentionsFilters
    {
        $accessible = $this->accessibleProjects->forUser($user);
        $project = AccessibleProjectFilter::resolve($accessible, $request->query->getString('project'));

        return new DashboardMentionsFilters(
            accessibleProjects: $accessible,
            selectedProjects: $project instanceof Project ? [$project] : $accessible,
            project: $project,
            unreadOnly: $request->query->getBoolean('unread'),
        );
    }
}
