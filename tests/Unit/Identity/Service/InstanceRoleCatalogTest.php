<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Service\InstanceRoleCatalog;
use App\Project\Enum\ProjectRole;
use App\Project\Security\ProjectPermission;
use PHPUnit\Framework\TestCase;

final class InstanceRoleCatalogTest extends TestCase
{
    public function testSeededRolesMirrorProjectRoleMatrices(): void
    {
        $byCode = [];
        foreach (InstanceRoleCatalog::definitions() as $definition) {
            $byCode[$definition['code']] = $definition['permission_keys'];
        }

        self::assertSame(
            [
                'ROLE_PROJECT_VIEWER',
                'ROLE_PROJECT_MEMBER',
                'ROLE_PROJECT_ADMIN',
                'ROLE_PROJECT_FULL',
                'ROLE_PROJECT_OWNER',
            ],
            array_keys($byCode),
        );
        self::assertSame(ProjectPermission::forRole(ProjectRole::Viewer), $byCode['ROLE_PROJECT_VIEWER']);
        self::assertSame(ProjectPermission::forRole(ProjectRole::Member), $byCode['ROLE_PROJECT_MEMBER']);
        self::assertSame(ProjectPermission::forRole(ProjectRole::Admin), $byCode['ROLE_PROJECT_ADMIN']);
        self::assertSame(ProjectPermission::forRole(ProjectRole::Full), $byCode['ROLE_PROJECT_FULL']);
        self::assertSame(ProjectPermission::forRole(ProjectRole::Owner), $byCode['ROLE_PROJECT_OWNER']);
        self::assertSame($byCode['ROLE_PROJECT_OWNER'], $byCode['ROLE_PROJECT_FULL']);
    }

    public function testSeededRolesOnlyUseProjectPermissionKeys(): void
    {
        $projectKeys = ProjectPermission::allValues();
        foreach (InstanceRoleCatalog::definitions() as $definition) {
            foreach ($definition['permission_keys'] as $key) {
                self::assertContains($key, $projectKeys, $definition['code'].' has non-project key '.$key);
                self::assertStringStartsWith('project.', $key);
            }
        }
    }

    public function testLegacyOperatorCodesAreListedForCleanup(): void
    {
        self::assertSame(
            ['ROLE_SUPPORT', 'ROLE_OPS_VIEWER', 'ROLE_PLATFORM', 'ROLE_NAV_EDITOR', 'ROLE_PROJECT_OPS'],
            InstanceRoleCatalog::legacyOperatorCodes(),
        );
    }
}
