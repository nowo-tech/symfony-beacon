<?php

declare(strict_types=1);

namespace App\Project\Security;

use App\Project\Enum\ProjectRole;

/**
 * Logical project capability keys derived from {@see ProjectRole}.
 *
 * Seed metadata lives in {@see \App\Project\Service\ProjectPermissionCatalog} (shared
 * `permission` table for catalog UI). Controllers MUST resolve access via
 * {@see \App\Project\Service\ProjectAccessService} / {@see \App\Project\Access\ProjectAccess::grants()}
 * (per-project membership) — not instance {@see \App\Identity\Security\InstancePermissionVoter}.
 *
 * @see docs/product/ROLES.md
 */
final class ProjectPermission
{
    /** Open project surfaces (Issues list, Performance, Analytics read, …). */
    public const string VIEW = 'project.view';

    /** Mutate issues: status, assignee, comments, priority, duplicate, saved views. */
    public const string ISSUES_TRIAGE = 'project.issues.triage';

    /** Direct members and group links. */
    public const string MEMBERS_MANAGE = 'project.members.manage';

    /** Create / rotate / revoke project API keys and DSNs. */
    public const string API_KEYS_MANAGE = 'project.api_keys.manage';

    /** Project Settings (retention, quotas, danger-zone suspend, read tokens, …). */
    public const string SETTINGS_MANAGE = 'project.settings.manage';

    /** Notification destinations and threshold rules. */
    public const string NOTIFICATIONS_MANAGE = 'project.notifications.manage';

    /** Create / revoke share links. */
    public const string SHARE_LINKS_MANAGE = 'project.share_links.manage';

    /** Delete the project (owner only). */
    public const string DELETE = 'project.delete';

    /**
     * @return list<string>
     */
    public static function allValues(): array
    {
        return [
            self::VIEW,
            self::ISSUES_TRIAGE,
            self::MEMBERS_MANAGE,
            self::API_KEYS_MANAGE,
            self::SETTINGS_MANAGE,
            self::NOTIFICATIONS_MANAGE,
            self::SHARE_LINKS_MANAGE,
            self::DELETE,
        ];
    }

    public static function isKnown(string $permission): bool
    {
        return \in_array(strtolower(trim($permission)), self::allValues(), true);
    }

    /**
     * Permissions granted by a membership role (viewer ⊂ member ⊂ admin ⊂ full = owner).
     *
     * @return list<string>
     */
    public static function forRole(ProjectRole $role): array
    {
        return match ($role) {
            ProjectRole::Viewer => [
                self::VIEW,
            ],
            ProjectRole::Member => [
                self::VIEW,
                self::ISSUES_TRIAGE,
            ],
            ProjectRole::Admin => [
                self::VIEW,
                self::ISSUES_TRIAGE,
                self::MEMBERS_MANAGE,
                self::API_KEYS_MANAGE,
                self::SETTINGS_MANAGE,
                self::NOTIFICATIONS_MANAGE,
                self::SHARE_LINKS_MANAGE,
            ],
            ProjectRole::Full, ProjectRole::Owner => self::allValues(),
        };
    }

    public static function roleGrants(ProjectRole $role, string $permission): bool
    {
        $key = strtolower(trim($permission));

        return \in_array($key, self::forRole($role), true);
    }

    private function __construct()
    {
    }
}
