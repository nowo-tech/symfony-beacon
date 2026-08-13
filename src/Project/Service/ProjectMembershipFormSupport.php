<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupRepository;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;

/**
 * Hydrates the project access graph and builds membership form choice maps.
 */
final readonly class ProjectMembershipFormSupport
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private UserGroupRepository $userGroupRepository,
    ) {
    }

    /**
     * @param array<int, int>          $groupMemberCounts
     * @param iterable<UserGroup>|null $orderedGroups     Defaults to all groups ordered for admin linking
     *
     * @return array<string, string>
     */
    public function groupChoicesForLinking(
        Project $project,
        array $groupMemberCounts = [],
        ?iterable $orderedGroups = null,
    ): array {
        $this->projectRepository->hydrateAccessGraph($project);

        return ProjectMembershipUiHelper::groupChoicesForLinking(
            $project,
            $orderedGroups ?? $this->userGroupRepository->findAllOrdered(),
            $groupMemberCounts,
        );
    }

    /**
     * @return array<string, string>
     */
    public function transferOwnershipChoices(Project $project, User $actor): array
    {
        $this->projectRepository->hydrateAccessGraph($project);

        return ProjectMembershipUiHelper::transferOwnershipChoices($project, $actor);
    }
}
