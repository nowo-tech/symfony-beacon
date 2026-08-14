<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminInstanceRoleController;
use App\Identity\Entity\InstanceRole;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AdminInstanceRoleUsersTest extends TestCase
{
    public function testAddUserFlashesWhenEmailUnknown(): void
    {
        $role = new InstanceRole()->setName('Support')->setCode('ROLE_SUPPORT');
        new ReflectionProperty(InstanceRole::class, 'id')->setValue($role, 4);
        new ReflectionProperty(InstanceRole::class, 'uuid')->setValue($role, '11111111-1111-7111-8111-111111111111');

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['email' => ' Missing@Example.com ']);

        $controller = new ReflectionClass(AdminInstanceRoleController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminInstanceRoleController::class, 'userRepository')->setValue($controller, $users);
        $session = $this->boot($controller, $form);

        $response = $controller->addUser(Request::create('/add', Request::METHOD_POST), $role);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/roles/users', $response->getTargetUrl());
        self::assertSame(['flash.roles.user_not_found'], $session->getFlashBag()->peek('error'));
    }

    public function testAddUserFlashesWhenAlreadyAssigned(): void
    {
        $role = new InstanceRole()->setName('Support')->setCode('ROLE_SUPPORT');
        new ReflectionProperty(InstanceRole::class, 'id')->setValue($role, 4);
        new ReflectionProperty(InstanceRole::class, 'uuid')->setValue($role, '11111111-1111-7111-8111-111111111111');

        $member = new User()->setEmail('member@example.com');
        $member->addInstanceRole($role);

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn($member);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['email' => 'member@example.com']);

        $controller = new ReflectionClass(AdminInstanceRoleController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminInstanceRoleController::class, 'userRepository')->setValue($controller, $users);
        $session = $this->boot($controller, $form);

        $response = $controller->addUser(Request::create('/add', Request::METHOD_POST), $role);
        self::assertSame(['flash.roles.user_already'], $session->getFlashBag()->peek('error'));
        self::assertSame('/admin/roles/users', $response->getTargetUrl());
    }

    private function boot(object $controller, FormInterface $form): Session
    {
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/roles/users');

        $admin = new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN']);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($admin, 'main', $admin->getRoles()));

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('security.token_storage', $tokenStorage);
        $container->set('request_stack', $stack);
        $controller->setContainer($container);

        return $session;
    }
}
