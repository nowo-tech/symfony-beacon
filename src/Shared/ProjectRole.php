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
        return $this === self::Owner || $this === self::Admin;
    }

    public function canManageApiKeys(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function canMutateProject(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }
}
