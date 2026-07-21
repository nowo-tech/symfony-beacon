<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Membership role within a project, with capability helpers.
 */
enum ProjectRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function canManageMembers(): bool
    {
        return self::Owner === $this || self::Admin === $this;
    }

    public function canManageApiKeys(): bool
    {
        return self::Owner === $this || self::Admin === $this;
    }

    public function canDeleteProject(): bool
    {
        return self::Owner === $this;
    }

    /**
     * Whether the role may mutate issues (status, assignee, comments, priority, duplicate, saved views).
     */
    public function canTriageIssues(): bool
    {
        return self::Viewer !== $this;
    }

    /**
     * Numeric rank for comparing roles (viewer < member < admin < owner).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Viewer => 0,
            self::Member => 1,
            self::Admin => 2,
            self::Owner => 3,
        };
    }
}
