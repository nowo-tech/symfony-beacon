<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\InstanceRole;
use App\Identity\Entity\User;
use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Repository\InstanceRoleRepository;
use App\Identity\Repository\UserRepository;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class IdentityRepositoryQueriesTest extends DatabaseWebTestCase
{
    public function testUserAndInstanceRoleRepositories(): void
    {
        [, $owner] = $this->bootWithDemoProject('identity-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $users = self::getContainer()->get(UserRepository::class);
        $roles = self::getContainer()->get(InstanceRoleRepository::class);
        $permissions = self::getContainer()->get(InstancePermissionRepository::class);

        $admin = new User()
            ->setEmail(' Admin@example.com ')
            ->setDisplayName('Admin Person')
            ->setPassword($hasher->hashPassword(new User(), 'secret'))
            ->setSlackUserId(' U123 ');
        $admin->setCreatedBy($owner);
        $admin->setUpdatedBy($owner);
        $admin->setRoles(['ROLE_ADMIN']);

        $staff = new User()
            ->setEmail('staff@example.com')
            ->setDisplayName('Staff Person')
            ->setPassword($hasher->hashPassword(new User(), 'secret'));
        $staff->setCreatedBy($owner);
        $staff->setUpdatedBy($owner);
        $staff->setAnonymizedAt(new DateTimeImmutable());

        $viewer = new User()
            ->setEmail('viewer@example.com')
            ->setDisplayName('Viewer')
            ->setPassword($hasher->hashPassword(new User(), 'secret'));

        $permission = new InstancePermission()
            ->setKey('project.view')
            ->setName('Project view')
            ->setCategory('access');
        $role = new InstanceRole()
            ->setName('Support')
            ->setCode('support')
            ->setDescription('Support role');
        $role->setCreatedBy($owner);
        $role->setUpdatedBy($owner);
        $role->addPermission($permission);
        $admin->addInstanceRole($role);
        $viewer->addInstanceRole($role);

        $disabledPermission = new InstancePermission()
            ->setKey('project.delete')
            ->setName('Delete')
            ->setCategory('access');
        $disabledRole = new InstanceRole()
            ->setName('Dormant')
            ->setCode('ROLE_DORMANT')
            ->setEnabled(false);
        $disabledRole->addPermission($disabledPermission);
        $staff->addInstanceRole($disabledRole);

        $em->persist($admin);
        $em->persist($staff);
        $em->persist($viewer);
        $em->persist($permission);
        $em->persist($disabledPermission);
        $em->persist($role);
        $em->persist($disabledRole);
        $em->flush();

        self::assertSame($admin->getId(), $users->findOneByEmail(' admin@example.com ')?->getId());
        self::assertSame(
            ['admin@example.com' => $admin, 'staff@example.com' => $staff],
            $users->findIndexedByEmails([' ', 'Admin@example.com', 'staff@example.com', 'admin@example.com']),
        );
        self::assertSame([], $users->findIndexedByEmails([' ', '']));
        self::assertNull($users->findOneBySlackUserId('   '));
        self::assertSame($admin->getId(), $users->findOneBySlackUserId(' U123 ')?->getId());

        self::assertCount(2, $users->findAllForAdminDirectory('Person', 10, 0));
        self::assertCount(1, $users->findAllForAdminDirectory(null, 1, 1));
        self::assertSame(2, $users->countForAdminDirectory('Person'));
        self::assertSame(1, $users->countAdmins());
        self::assertSame(1, $users->countAdmins(excludeAnonymized: true));

        $saved = new User()
            ->setEmail('saved@example.com')
            ->setDisplayName('Saved User')
            ->setPassword($hasher->hashPassword(new User(), 'secret'));
        $users->save($saved, flush: false);
        self::assertNull($saved->getId());
        $users->save($saved);
        self::assertNotNull($saved->getId());

        self::assertCount(1, $roles->findAllOrdered('Supp', 10, 0));
        self::assertSame(2, $roles->countAllOrdered('o'));
        self::assertSame([1 => ['permissions' => 1, 'users' => 2], 2 => ['permissions' => 1, 'users' => 1]], $this->normalizedRoleCounts($roles->countByRoleIds([(int) $role->getId(), (int) $disabledRole->getId()])));
        self::assertSame([], $roles->countByRoleIds([]));
        self::assertSame($role->getId(), $roles->findOneByCode('support')?->getId());
        $roles->hydrateDetail($role);
        self::assertSame(2, $roles->countAssignedUsers($role));
        self::assertSame(['project.view'], $roles->findPermissionKeysForUserId((int) $admin->getId()));
        self::assertSame($permission->getId(), $permissions->findOneByKey(' PROJECT.VIEW ')?->getId());
        self::assertSame(['project.delete', 'project.view'], $permissions->findAllKeys());
        self::assertCount(1, $permissions->findAllOrdered('delete'));
    }

    /**
     * @param array<int, array{permissions: int, users: int}> $counts
     *
     * @return array<int, array{permissions: int, users: int}>
     */
    private function normalizedRoleCounts(array $counts): array
    {
        $normalized = [];
        foreach ($counts as $roleId => $row) {
            $normalized[$roleId - (min(array_keys($counts)) - 1)] = $row;
        }

        return $normalized;
    }
}
