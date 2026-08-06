<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Setup\AdminUserProvisioner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminUserProvisionerTest extends TestCase
{
    public function testAdminExistsWhenUsersPresent(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('count')->willReturn(2);

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $provisioner = new AdminUserProvisioner($users, $hasher);

        self::assertTrue($provisioner->adminExists());
    }

    public function testAdminDoesNotExistWhenEmpty(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('count')->willReturn(0);

        $hasher = $this->createStub(UserPasswordHasherInterface::class);

        self::assertFalse((new AdminUserProvisioner($users, $hasher))->adminExists());
    }

    public function testCreateAdminHashesPasswordAndSaves(): void
    {
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed-secret');

        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('save')->with(self::callback(
            static function (User $user): bool {
                self::assertSame('admin@example.com', $user->getEmail());
                self::assertSame('admin', $user->getDisplayName());
                self::assertSame('hashed-secret', $user->getPassword());
                self::assertContains('ROLE_ADMIN', $user->getRoles());
                self::assertNotNull($user->getPasswordChangedAt());

                return true;
            },
        ));

        $provisioner = new AdminUserProvisioner($users, $hasher);
        $provisioner->createAdmin([
            'email' => 'admin@example.com',
            'password' => 'plain',
        ]);
    }

    public function testCreateAdminUsesFallbackDisplayNameAndDefaultRoles(): void
    {
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('save')->with(self::callback(
            static function (User $user): bool {
                self::assertSame('Admin', $user->getDisplayName());
                self::assertContains('ROLE_ADMIN', $user->getRoles());

                return true;
            },
        ));

        (new AdminUserProvisioner($users, $hasher))->createAdmin([
            'email' => 'not-an-email',
            'password' => 'x',
            'roles' => [],
        ]);
    }
}
