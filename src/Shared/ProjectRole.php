<?php

declare(strict_types=1);

namespace App\Shared;

enum ProjectRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

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
}
