<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminInstancePermissionController;
use App\Identity\Controller\AdminInstanceRoleController;
use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\InstanceRole;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class AdminRbacControllerHelpersTest extends TestCase
{
    public function testResolveReturnRouteAllowsDetailRoutesOnly(): void
    {
        $controller = new ReflectionClass(AdminInstanceRoleController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminInstanceRoleController::class, 'resolveReturnRoute');

        self::assertSame('admin_roles_show', $method->invoke($controller, 'admin_roles_show'));
        self::assertSame('admin_roles_users', $method->invoke($controller, 'admin_roles_users'));
        self::assertSame('admin_roles_permissions', $method->invoke($controller, 'admin_roles_permissions'));
        self::assertSame('admin_roles_show', $method->invoke($controller, 'admin_roles'));
        self::assertSame('admin_roles_show', $method->invoke($controller, 'evil_route'));
    }

    public function testPermissionsFormDataMapsAssignedPermissions(): void
    {
        $assigned = new InstancePermission()->setKey('project.view');
        new ReflectionProperty(InstancePermission::class, 'id')->setValue($assigned, 5);
        $other = new InstancePermission()->setKey('project.delete');
        new ReflectionProperty(InstancePermission::class, 'id')->setValue($other, 9);

        $role = new InstanceRole();
        $role->addPermission($assigned);

        $controller = new ReflectionClass(AdminInstanceRoleController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminInstanceRoleController::class, 'permissionsFormData');
        $data = $method->invoke($controller, $role, [5, 9]);

        self::assertTrue($data['permission_5']);
        self::assertFalse($data['permission_9']);
    }

    public function testPermissionFormLocaleOptionsOrdersDefaultFirst(): void
    {
        $controller = new ReflectionClass(AdminInstancePermissionController::class)->newInstanceWithoutConstructor();
        $bag = new ParameterBag([
            'kernel.enabled_locales' => ['es', 'en', 'fr', 'en'],
            'default_locale' => 'en',
        ]);
        $container = new Container();
        $container->set('parameter_bag', $bag);
        $controller->setContainer($container);

        $method = new ReflectionMethod(AdminInstancePermissionController::class, 'permissionFormLocaleOptions');
        $options = $method->invoke($controller);

        self::assertSame('en', $options['default_locale']);
        self::assertSame(['en', 'es', 'fr'], $options['enabled_locales']);
    }
}
