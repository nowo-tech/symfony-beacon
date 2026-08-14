<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminUserController;
use App\Identity\UserActionType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class AdminUserControllerAuditChoicesTest extends TestCase
{
    public function testAuditActionChoicesMapsTranslationKeys(): void
    {
        $controller = new ReflectionClass(AdminUserController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminUserController::class, 'auditActionChoices');
        $choices = $method->invoke($controller, [
            UserActionType::UserCreated,
            UserActionType::UserRoleChanged,
        ]);

        self::assertSame([
            'users.activity.action.user.created' => 'user.created',
            'users.activity.action.user.role_changed' => 'user.role_changed',
        ], $choices);
    }
}
