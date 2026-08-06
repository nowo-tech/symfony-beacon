<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project;

use App\Project\Access\ProjectAccess;
use App\Project\Enum\ProjectRole;
use PHPUnit\Framework\TestCase;

final class ProjectAccessTest extends TestCase
{
    public function testDelegatesCapabilitiesToRole(): void
    {
        $access = new ProjectAccess(ProjectRole::Admin, viaGroup: true);

        self::assertTrue($access->canManageMembers());
        self::assertTrue($access->canManageApiKeys());
        self::assertFalse($access->canDeleteProject());
        self::assertTrue($access->canTriageIssues());
        self::assertTrue($access->viaGroup);
        self::assertNull($access->directMembership);
    }

    public function testViewerCannotTriage(): void
    {
        $access = new ProjectAccess(ProjectRole::Viewer);

        self::assertFalse($access->canTriageIssues());
        self::assertFalse($access->canManageMembers());
    }
}
