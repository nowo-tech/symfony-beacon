<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Dto\DashboardMentionsFilters;
use App\Project\Service\DashboardProjectSelectionResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves dashboard Mentions filters from the request and actor access.
 */
final readonly class DashboardMentionsFilterResolver
{
    public function __construct(
        private DashboardProjectSelectionResolver $projectSelection,
    ) {
    }

    public function resolve(User $user, Request $request): DashboardMentionsFilters
    {
        $selection = $this->projectSelection->resolve($user, $request);

        return new DashboardMentionsFilters(
            accessibleProjects: $selection['accessible'],
            selectedProjects: $selection['selected'],
            project: $selection['project'],
            unreadOnly: $request->query->getBoolean('unread'),
        );
    }
}
