<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Project\Service\ProjectPermissionCatalog;

/**
 * Typed permission keys (Security attributes for {@see InstancePermissionVoter} when used).
 *
 * Built-in catalog is project membership metadata only ({@see ProjectPermissionCatalog}).
 * Administration surfaces stay gated by {@code ROLE_ADMIN}.
 *
 * @see docs/product/ROLES.md
 */
final class Permission
{
    /** Lowercase dotted keys with ≥2 segments (e.g. project.view). */
    public const string KEY_PATTERN = '/^[a-z][a-z0-9_.]*(\.[a-z][a-z0-9_]*)+$/';

    /**
     * Whether the attribute is a capability key (not Symfony ROLE_* / IS_*).
     */
    public static function isDottedCapability(string $attribute): bool
    {
        if (!str_contains($attribute, '.')) {
            return false;
        }

        if (str_starts_with($attribute, 'ROLE_') || str_starts_with($attribute, 'IS_')) {
            return false;
        }

        return true;
    }

    /**
     * Built-in catalog keys (project.*; excludes custom DB-only permissions).
     *
     * @return list<string>
     */
    public static function allValues(): array
    {
        $keys = array_map(
            static fn (array $definition): string => $definition['key'],
            ProjectPermissionCatalog::definitions(),
        );

        return array_values(array_unique($keys));
    }

    public static function isKnown(string $permission): bool
    {
        return \in_array(strtolower(trim($permission)), self::allValues(), true);
    }

    private function __construct()
    {
    }
}
