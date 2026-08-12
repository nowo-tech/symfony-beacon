<?php

declare(strict_types=1);

namespace App\Project\Access;

use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Security\ProjectPermission;

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

    public function grants(string $permission): bool
    {
        return $this->role->grants($permission);
    }

    public function grantsAny(string ...$permissions): bool
    {
        return array_any($permissions, fn(string $permission): bool => $this->grants($permission));
    }

    /** Whether the role may open project product surfaces. */
    public function canView(): bool
    {
        return $this->grants(ProjectPermission::VIEW);
    }

    /**
     * Whether Settings (and related admin sections) may be opened.
     *
     * Viewers/members with only view/triage must not see membership emails, DSNs, tokens, etc.
     */
    public function canOpenSettings(): bool
    {
        return $this->grantsAny(
            ProjectPermission::MEMBERS_MANAGE,
            ProjectPermission::API_KEYS_MANAGE,
            ProjectPermission::SETTINGS_MANAGE,
            ProjectPermission::NOTIFICATIONS_MANAGE,
            ProjectPermission::SHARE_LINKS_MANAGE,
            ProjectPermission::DELETE,
        );
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

    /** Whether the role may edit project Settings (retention, quotas, tokens, …). */
    public function canManageSettings(): bool
    {
        return $this->role->canManageSettings();
    }

    /** Whether the role may manage notification destinations / thresholds. */
    public function canManageNotifications(): bool
    {
        return $this->role->canManageNotifications();
    }

    /** Whether the role may create or revoke share links. */
    public function canManageShareLinks(): bool
    {
        return $this->role->canManageShareLinks();
    }

    /** Whether the role may delete the project (owner or full). */
    public function canDeleteProject(): bool
    {
        return $this->role->canDeleteProject();
    }

    /** Primary owner only (transfer / last-owner); {@see ProjectRole::Full} is false. */
    public function isPrimaryOwner(): bool
    {
        return $this->role->isPrimaryOwner();
    }

    /** Whether the role may mutate issues / triage / comments / saved views. */
    public function canTriageIssues(): bool
    {
        return $this->role->canTriageIssues();
    }
}
