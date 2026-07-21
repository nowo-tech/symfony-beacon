<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use InvalidArgumentException;

/**
 * Ensures issue assignees belong to the project (membership or group access).
 */
final readonly class IssueAssigneeGuard
{
    public function __construct(
        private ProjectAccessService $projectAccess,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the user cannot access the project
     */
    public function assertAssignable(Project $project, ?User $assignee): void
    {
        if (!$assignee instanceof User) {
            return;
        }

        if (!$this->projectAccess->resolveAccess($project, $assignee) instanceof ProjectAccess) {
            throw new InvalidArgumentException('assignee_not_member');
        }
    }
}
