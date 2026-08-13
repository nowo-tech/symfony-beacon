<?php

declare(strict_types=1);

namespace App\Project\Port;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Exception\ProjectAccessException;

/**
 * Admin-only port for unlinking project memberships and group access from other modules.
 */
interface ProjectMembershipAdminPort
{
    /**
     * @throws ProjectAccessException
     */
    public function unlinkMembership(Project $project, User $actor, ProjectMembership $membership): void;

    /**
     * @throws ProjectAccessException
     */
    public function unlinkGroupAccess(Project $project, User $actor, ProjectGroupAccess $groupAccess): void;
}
