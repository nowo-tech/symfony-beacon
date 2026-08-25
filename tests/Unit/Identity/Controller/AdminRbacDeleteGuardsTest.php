<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminInstancePermissionController;
use App\Identity\Controller\AdminInstanceRoleController;
use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\InstanceRole;
use App\Identity\Repository\InstanceRoleRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminRbacDeleteGuardsTest extends TestCase
{
    public function testDeleteRoleBlockedWhenSystem(): void
    {
        $role = new InstanceRole()->setName('Admin')->setCode('ROLE_ADMIN')->setSystem(true);
        new ReflectionProperty(InstanceRole::class, 'uuid')->setValue($role, '11111111-1111-7111-8111-111111111111');

        $controller = new ReflectionClass(AdminInstanceRoleController::class)->newInstanceWithoutConstructor();
        $session = $this->boot($controller, ['admin_roles_show' => '/admin/roles/show']);

        $response = $controller->delete(Request::create('/x', Request::METHOD_POST), $role);
        self::assertTrue($response->isRedirection());
        self::assertSame('/admin/roles/show', $response->headers->get('Location'));
        self::assertSame(['flash.roles.system_locked'], $session->getFlashBag()->peek('error'));
    }

    public function testDeleteRoleBlockedWhenUsersAssigned(): void
    {
        $role = new InstanceRole()->setName('Support')->setCode('ROLE_SUPPORT')->setSystem(false);
        new ReflectionProperty(InstanceRole::class, 'id')->setValue($role, 4);
        new ReflectionProperty(InstanceRole::class, 'uuid')->setValue($role, '22222222-2222-7222-8222-222222222222');

        $roles = $this->createStub(InstanceRoleRepository::class);
        $roles->method('hydrateDetail');
        $roles->method('countAssignedUsers')->willReturn(3);

        $controller = new ReflectionClass(AdminInstanceRoleController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminInstanceRoleController::class, 'roleRepository')->setValue($controller, $roles);
        $session = $this->boot($controller, ['admin_roles_show' => '/admin/roles/show']);

        $response = $controller->delete(Request::create('/x', Request::METHOD_POST), $role);
        self::assertSame('/admin/roles/show', $response->headers->get('Location'));
        self::assertSame(['flash.roles.in_use'], $session->getFlashBag()->peek('error'));
    }

    public function testDeletePermissionBlockedWhenSystem(): void
    {
        $permission = new InstancePermission()->setKey('project.view')->setName('View')->setSystem(true);
        new ReflectionProperty(InstancePermission::class, 'uuid')->setValue(
            $permission,
            '33333333-3333-7333-8333-333333333333',
        );

        $controller = new ReflectionClass(AdminInstancePermissionController::class)->newInstanceWithoutConstructor();
        $session = $this->boot($controller, ['admin_permissions' => '/admin/permissions']);

        $response = $controller->delete(Request::create('/x', Request::METHOD_POST), $permission);
        self::assertSame('/admin/permissions', $response->headers->get('Location'));
        self::assertSame(['flash.permissions.system_locked'], $session->getFlashBag()->peek('error'));
    }

    public function testEditGetRedirectsToShowWithEditFlag(): void
    {
        $role = new InstanceRole()->setName('Support')->setCode('ROLE_SUPPORT');
        new ReflectionProperty(InstanceRole::class, 'uuid')->setValue($role, '11111111-1111-7111-8111-111111111111');

        $controller = new ReflectionClass(AdminInstanceRoleController::class)->newInstanceWithoutConstructor();
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []): string => '/'.$route.'?'.http_build_query($params),
        );
        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        $response = $controller->edit(Request::create('/admin/roles/x/edit'), $role);
        self::assertTrue($response->isRedirection());
        self::assertStringContainsString('admin_roles_show', $response->headers->get('Location'));
        self::assertStringContainsString('edit=1', $response->headers->get('Location'));
    }

    /**
     * @param array<string, string> $routes
     */
    private function boot(object $controller, array $routes): Session
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route): string => $routes[$route] ?? '/'.$route,
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = new Container();
        $container->set('router', $router);
        $container->set('request_stack', $stack);
        $controller->setContainer($container);

        return $session;
    }
}
