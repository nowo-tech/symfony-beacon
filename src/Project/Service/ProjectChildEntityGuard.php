<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectRole;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ensures a project-scoped entity belongs to the URL project and the actor is admin.
 */
final readonly class ProjectChildEntityGuard
{
    public function __construct(
        private ProjectAccessService $projectAccess,
    ) {
    }

    public function requireManagedChild(string $projectUuid, ?Project $entityProject, User $user): Project
    {
        if (!$entityProject instanceof Project || $entityProject->getUuid() !== $projectUuid) {
            throw new NotFoundHttpException();
        }

        $this->projectAccess->requireRole($entityProject, $user, ProjectRole::Admin);

        return $entityProject;
    }
}
