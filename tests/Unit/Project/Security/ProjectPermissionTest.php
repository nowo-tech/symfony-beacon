<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Security;

use App\Project\Enum\ProjectRole;
use App\Project\Security\ProjectPermission;
use PHPUnit\Framework\TestCase;

final class ProjectPermissionTest extends TestCase
{
    public function testViewerIsReadOnly(): void
    {
        self::assertTrue(ProjectPermission::roleGrants(ProjectRole::Viewer, ProjectPermission::VIEW));
        self::assertFalse(ProjectPermission::roleGrants(ProjectRole::Viewer, ProjectPermission::ISSUES_TRIAGE));
        self::assertFalse(ProjectPermission::roleGrants(ProjectRole::Viewer, ProjectPermission::MEMBERS_MANAGE));
        self::assertFalse(ProjectPermission::roleGrants(ProjectRole::Viewer, ProjectPermission::DELETE));
    }

    public function testMemberCanTriageButNotSettings(): void
    {
        self::assertTrue(ProjectRole::Member->grants(ProjectPermission::ISSUES_TRIAGE));
        self::assertFalse(ProjectRole::Member->canManageSettings());
        self::assertFalse(ProjectRole::Member->canManageApiKeys());
    }

    public function testAdminHasOpsWithoutDelete(): void
    {
        $perms = ProjectPermission::forRole(ProjectRole::Admin);
        self::assertContains(ProjectPermission::SETTINGS_MANAGE, $perms);
        self::assertContains(ProjectPermission::NOTIFICATIONS_MANAGE, $perms);
        self::assertContains(ProjectPermission::SHARE_LINKS_MANAGE, $perms);
        self::assertNotContains(ProjectPermission::DELETE, $perms);
        self::assertFalse(ProjectRole::Admin->canDeleteProject());
    }

    public function testOwnerHasEveryKey(): void
    {
        self::assertSame(
            ProjectPermission::allValues(),
            ProjectPermission::forRole(ProjectRole::Owner),
        );
        self::assertTrue(ProjectRole::Owner->canDeleteProject());
    }

    public function testFullMatchesOwnerPermissionsWithoutBeingPrimaryOwner(): void
    {
        self::assertSame(
            ProjectPermission::forRole(ProjectRole::Owner),
            ProjectPermission::forRole(ProjectRole::Full),
        );
        self::assertTrue(ProjectRole::Full->canDeleteProject());
        self::assertSame(ProjectRole::Owner->rank(), ProjectRole::Full->rank());
        self::assertTrue(ProjectRole::Owner->isPrimaryOwner());
        self::assertFalse(ProjectRole::Full->isPrimaryOwner());
    }
}
