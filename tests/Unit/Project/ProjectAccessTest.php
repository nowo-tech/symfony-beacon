<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project;

use App\Project\Access\ProjectAccess;
use App\Project\Enum\ProjectRole;
use App\Project\Security\ProjectPermission;
use PHPUnit\Framework\TestCase;

final class ProjectAccessTest extends TestCase
{
    public function testDelegatesCapabilitiesToRole(): void
    {
        $access = new ProjectAccess(ProjectRole::Admin, viaGroup: true);

        self::assertTrue($access->canManageMembers());
        self::assertTrue($access->canManageApiKeys());
        self::assertTrue($access->canManageSettings());
        self::assertTrue($access->canManageNotifications());
        self::assertTrue($access->canManageShareLinks());
        self::assertFalse($access->canDeleteProject());
        self::assertTrue($access->canTriageIssues());
        self::assertTrue($access->grants(ProjectPermission::API_KEYS_MANAGE));
        self::assertTrue($access->viaGroup);
        self::assertNull($access->directMembership);
    }

    public function testViewerCannotTriage(): void
    {
        $access = new ProjectAccess(ProjectRole::Viewer);

        self::assertTrue($access->canView());
        self::assertFalse($access->canTriageIssues());
        self::assertFalse($access->canManageMembers());
        self::assertFalse($access->grants(ProjectPermission::SETTINGS_MANAGE));
        self::assertFalse($access->canOpenSettings());
        self::assertFalse($access->grantsAny(ProjectPermission::SETTINGS_MANAGE, ProjectPermission::DELETE));
    }

    public function testFullIsNotPrimaryOwnerButCanDelete(): void
    {
        $access = new ProjectAccess(ProjectRole::Full);

        self::assertTrue($access->canDeleteProject());
        self::assertTrue($access->canOpenSettings());
        self::assertFalse($access->isPrimaryOwner());
        self::assertTrue(new ProjectAccess(ProjectRole::Owner)->isPrimaryOwner());
    }
}
