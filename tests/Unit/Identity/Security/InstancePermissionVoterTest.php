<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\User;
use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Repository\InstanceRoleRepository;
use App\Identity\Security\InstancePermissionVoter;
use App\Project\Entity\Project;
use App\Project\Security\ProjectPermission;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class InstancePermissionVoterTest extends TestCase
{
    private InstancePermissionRepository&MockObject $permissions;
    private InstanceRoleRepository&MockObject $roles;
    private InstancePermissionVoter $voter;

    protected function setUp(): void
    {
        $this->permissions = $this->createMock(InstancePermissionRepository::class);
        $this->roles = $this->createMock(InstanceRoleRepository::class);
        $this->voter = new InstancePermissionVoter($this->permissions, $this->roles);
    }

    public function testIgnoresRoleAttributes(): void
    {
        $user = new User();
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, null, ['ROLE_ADMIN']));
    }

    public function testAdminGrantedAnyKnownPermission(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $this->permissions->expects(self::never())->method('findOneByKey');

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, ['project.view']),
        );
    }

    public function testAssignedPermissionGranted(): void
    {
        $user = new User();
        $ref = new ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 7);

        $permission = new InstancePermission();
        $permission->setKey('project.settings.manage');

        $this->permissions->expects(self::any())->method('findOneByKey')->with('project.settings.manage')->willReturn($permission);
        $this->roles->expects(self::any())->method('findPermissionKeysForUserId')->with(7)->willReturn(['project.settings.manage']);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, ['project.settings.manage']),
        );
    }

    public function testUnknownPermissionDenied(): void
    {
        $user = new User();
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->permissions->method('findOneByKey')->willReturn(null);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, null, ['admin.unknown.thing']),
        );
    }

    public function testAbstainsWhenSubjectIsProject(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER']);
        $ref = new ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 7);

        $this->permissions->expects(self::never())->method('findOneByKey');
        $this->roles->expects(self::never())->method('findPermissionKeysForUserId');

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $project = new Project();

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, $project, [ProjectPermission::DELETE]),
        );
    }

    public function testAdminStillGrantedWithoutProjectSubject(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [ProjectPermission::DELETE]),
        );
    }
}
