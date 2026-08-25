<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project;

use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Security\ProjectPermission;
use PHPUnit\Framework\TestCase;

final class CoverageRegressionTest extends TestCase
{
    public function testProjectApiKeyReturnsFalseWhenNoSecretIsStored(): void
    {
        self::assertFalse(new ProjectApiKey()->matchesSecret('missing-secret'));
    }

    public function testProjectMembershipAndGroupAccessExposeCreationTimestamps(): void
    {
        self::assertGreaterThan(0, new ProjectMembership()->getCreatedAt()->getTimestamp());
        self::assertGreaterThan(0, new ProjectGroupAccess()->getCreatedAt()->getTimestamp());
    }

    public function testProjectRolePermissionsMirrorRoleMatrix(): void
    {
        self::assertSame(ProjectPermission::forRole(ProjectRole::Admin), ProjectRole::Admin->permissions());
    }
}
