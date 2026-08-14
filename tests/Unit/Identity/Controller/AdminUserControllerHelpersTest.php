<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminUserController;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AdminUserControllerHelpersTest extends TestCase
{
    public function testIsAppAdminDetectsRoleAdmin(): void
    {
        $controller = new ReflectionClass(AdminUserController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminUserController::class, 'isAppAdmin');

        $admin = new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $member = new User()->setEmail('user@example.com')->setRoles(['ROLE_USER']);

        self::assertTrue($method->invoke($controller, $admin));
        self::assertFalse($method->invoke($controller, $member));
    }

    public function testCountAdminsDelegatesToRepository(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('countAdmins')->willReturn(3);

        $controller = new ReflectionClass(AdminUserController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminUserController::class, 'userRepository')->setValue($controller, $users);

        $method = new ReflectionMethod(AdminUserController::class, 'countAdmins');
        self::assertSame(3, $method->invoke($controller));
    }
}
