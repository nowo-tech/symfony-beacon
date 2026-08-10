<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Project\Enum\ProjectRole;
use App\Project\Security\ProjectPermission;

/**
 * Built-in InstanceRoles whose permission matrices mirror {@see ProjectRole}.
 *
 * These document the `project.*` capability sets in Administration → Roles.
 * Runtime product access remains per-project membership ({@see ProjectRole} on
 * membership / group links via {@see \App\Project\Service\ProjectAccessService}).
 *
 * Legacy admin-operator codes are listed for cleanup only.
 *
 * @see docs/product/ROLES.md
 */
final class InstanceRoleCatalog
{
    /**
     * Codes previously upserted as delegated Administration operators (removed on seed).
     *
     * @return list<string>
     */
    public static function legacyOperatorCodes(): array
    {
        return [
            'ROLE_SUPPORT',
            'ROLE_OPS_VIEWER',
            'ROLE_PLATFORM',
            'ROLE_NAV_EDITOR',
            'ROLE_PROJECT_OPS',
        ];
    }

    /**
     * @return list<array{code: string, name: string, description: string, permission_keys: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'ROLE_PROJECT_VIEWER',
                'name' => 'Project viewer',
                'description' => 'Read-only project access (mirrors ProjectRole viewer). Product access still requires project membership.',
                'permission_keys' => ProjectPermission::forRole(ProjectRole::Viewer),
            ],
            [
                'code' => 'ROLE_PROJECT_MEMBER',
                'name' => 'Project member',
                'description' => 'View project and triage issues (mirrors ProjectRole member). Product access still requires project membership.',
                'permission_keys' => ProjectPermission::forRole(ProjectRole::Member),
            ],
            [
                'code' => 'ROLE_PROJECT_ADMIN',
                'name' => 'Project admin',
                'description' => 'Members, API keys, settings, notifications, and share links (mirrors ProjectRole admin). Product access still requires project membership.',
                'permission_keys' => ProjectPermission::forRole(ProjectRole::Admin),
            ],
            [
                'code' => 'ROLE_PROJECT_FULL',
                'name' => 'Project full',
                'description' => 'Full project.* matrix including delete (mirrors ProjectRole full). Not primary owner — cannot transfer ownership. Product access still requires project membership.',
                'permission_keys' => ProjectPermission::forRole(ProjectRole::Full),
            ],
            [
                'code' => 'ROLE_PROJECT_OWNER',
                'name' => 'Project owner',
                'description' => 'Full project control including delete and primary ownership (mirrors ProjectRole owner). Product access still requires project membership.',
                'permission_keys' => ProjectPermission::forRole(ProjectRole::Owner),
            ],
        ];
    }
}
