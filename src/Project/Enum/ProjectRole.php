<?php

declare(strict_types=1);

namespace App\Project\Enum;

use App\Project\Security\ProjectPermission;

/**
 * Membership role within a project, with capability helpers.
 *
 * Logical permission keys live in {@see ProjectPermission}; helpers below are
 * convenience wrappers over that matrix.
 */
enum ProjectRole: string
{
    case Owner = 'owner';
    /** Full project.* matrix (same as owner) without primary-ownership (transfer / last-owner). */
    case Full = 'full';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function grants(string $permission): bool
    {
        return ProjectPermission::roleGrants($this, $permission);
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ProjectPermission::forRole($this);
    }

    public function canManageMembers(): bool
    {
        return $this->grants(ProjectPermission::MEMBERS_MANAGE);
    }

    public function canManageApiKeys(): bool
    {
        return $this->grants(ProjectPermission::API_KEYS_MANAGE);
    }

    public function canManageSettings(): bool
    {
        return $this->grants(ProjectPermission::SETTINGS_MANAGE);
    }

    public function canManageNotifications(): bool
    {
        return $this->grants(ProjectPermission::NOTIFICATIONS_MANAGE);
    }

    public function canManageShareLinks(): bool
    {
        return $this->grants(ProjectPermission::SHARE_LINKS_MANAGE);
    }

    public function canDeleteProject(): bool
    {
        return $this->grants(ProjectPermission::DELETE);
    }

    /**
     * Whether the role may mutate issues (status, assignee, comments, priority, duplicate, saved views).
     */
    public function canTriageIssues(): bool
    {
        return $this->grants(ProjectPermission::ISSUES_TRIAGE);
    }

    /**
     * Numeric rank for comparing roles (viewer < member < admin < full = owner).
     *
     * Primary ownership gates (transfer / last-owner) MUST use exact {@see self::Owner},
     * not rank — {@see self::Full} shares rank 3 for maxRole/permission comparison only.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Viewer => 0,
            self::Member => 1,
            self::Admin => 2,
            self::Full, self::Owner => 3,
        };
    }

    /** Whether this role is the primary project owner (transfer / last-owner). */
    public function isPrimaryOwner(): bool
    {
        return self::Owner === $this;
    }
}
