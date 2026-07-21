<?php

declare(strict_types=1);

namespace App\Project\Access;

use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;

/**
 * Effective project access for a user (direct membership and/or via groups).
 *
 * Role is the maximum of direct and group-derived roles. Owner is never granted via groups.
 */
final readonly class ProjectAccess
{
    public function __construct(
        public ProjectRole $role,
        public ?ProjectMembership $directMembership = null,
        public bool $viaGroup = false,
    ) {
    }

    /** Whether the role may add/change/remove members and group links. */
    public function canManageMembers(): bool
    {
        return $this->role->canManageMembers();
    }

    /** Whether the role may create or revoke project API keys. */
    public function canManageApiKeys(): bool
    {
        return $this->role->canManageApiKeys();
    }

    /** Whether the role may delete the project (owner only). */
    public function canDeleteProject(): bool
    {
        return $this->role->canDeleteProject();
    }

    /** Whether the role may mutate issues / triage / comments / saved views. */
    public function canTriageIssues(): bool
    {
        return $this->role->canTriageIssues();
    }
}
