<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Entity;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\InstanceRole;
use App\Identity\Entity\User;
use PHPUnit\Framework\TestCase;

final class InstanceRoleTest extends TestCase
{
    public function testPermissionsAndAuditUsersAreManaged(): void
    {
        $role = (new InstanceRole())
            ->setName(' Support ')
            ->setCode(' support ')
            ->setDescription(' Handles incidents ');

        $permission = (new InstancePermission())->setKey('project.view');
        $role->addPermission($permission);
        self::assertTrue($role->hasPermissionKey(' PROJECT.VIEW '));

        $role->removePermission($permission);
        self::assertFalse($role->hasPermissionKey('project.view'));
        self::assertCount(0, $role->getPermissions());

        $user = new User();
        $role->setCreatedBy($user);
        $role->setUpdatedBy($user);

        self::assertSame($user, $role->getCreatedBy());
        self::assertSame($user, $role->getUpdatedBy());
    }
}
