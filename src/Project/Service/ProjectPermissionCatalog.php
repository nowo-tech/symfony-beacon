<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Project\Security\ProjectPermission;

/**
 * Seed metadata for project capability keys ({@see ProjectPermission}).
 *
 * Rows land in the shared `permission` table for Administration catalog visibility.
 * Runtime project checks stay on {@see ProjectAccessService}
 * (membership role matrix), not {@see \App\Identity\Security\InstancePermissionVoter}.
 *
 * @see docs/product/ROLES.md
 */
final class ProjectPermissionCatalog
{
    /**
     * @return list<array{key: string, name: string, description: string, category: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => ProjectPermission::VIEW,
                'name' => 'View project',
                'description' => 'Open the project and read product surfaces (Issues list, Performance, Analytics).',
                'category' => 'access',
            ],
            [
                'key' => ProjectPermission::ISSUES_TRIAGE,
                'name' => 'Triage issues',
                'description' => 'Mutate issues: status, assignee, comments, priority, duplicate, and saved views.',
                'category' => 'issues',
            ],
            [
                'key' => ProjectPermission::MEMBERS_MANAGE,
                'name' => 'Manage members',
                'description' => 'Add or remove direct members and project group links; change membership roles.',
                'category' => 'collaboration',
            ],
            [
                'key' => ProjectPermission::API_KEYS_MANAGE,
                'name' => 'Manage API keys',
                'description' => 'Create, rotate, and revoke project API keys and DSN credentials.',
                'category' => 'integration',
            ],
            [
                'key' => ProjectPermission::SETTINGS_MANAGE,
                'name' => 'Manage project settings',
                'description' => 'Edit project Settings: retention, quotas, suspend ingest, clear history, read tokens.',
                'category' => 'settings',
            ],
            [
                'key' => ProjectPermission::NOTIFICATIONS_MANAGE,
                'name' => 'Manage notifications',
                'description' => 'Configure notification destinations and threshold alert rules.',
                'category' => 'settings',
            ],
            [
                'key' => ProjectPermission::SHARE_LINKS_MANAGE,
                'name' => 'Manage share links',
                'description' => 'Create and revoke time-limited project or issue share links.',
                'category' => 'collaboration',
            ],
            [
                'key' => ProjectPermission::DELETE,
                'name' => 'Delete project',
                'description' => 'Permanently delete the project (owner only).',
                'category' => 'danger',
            ],
        ];
    }
}
