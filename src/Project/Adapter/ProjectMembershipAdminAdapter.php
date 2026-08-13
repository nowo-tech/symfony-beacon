<?php

declare(strict_types=1);

namespace App\Project\Adapter;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Exception\ProjectAccessException;
use App\Project\Port\ProjectMembershipAdminPort;
use App\Project\Service\ProjectMembershipManager;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Bridges cross-module admin unlink actions to the Project membership domain service.
 */
#[AsAlias(ProjectMembershipAdminPort::class)]
final readonly class ProjectMembershipAdminAdapter implements ProjectMembershipAdminPort
{
    public function __construct(
        private ProjectMembershipManager $membershipManager,
    ) {
    }

    /**
     * @throws ProjectAccessException
     */
    public function unlinkMembership(Project $project, User $actor, ProjectMembership $membership): void
    {
        $this->membershipManager->remove($project, $actor, $membership);
    }

    /**
     * @throws ProjectAccessException
     */
    public function unlinkGroupAccess(Project $project, User $actor, ProjectGroupAccess $groupAccess): void
    {
        $this->membershipManager->removeGroup($project, $actor, $groupAccess);
    }
}
