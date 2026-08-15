<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminUserController;
use App\Identity\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AdminUserControllerHelpersTest extends TestCase
{
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
