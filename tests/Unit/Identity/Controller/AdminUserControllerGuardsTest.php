<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminUserController;
use App\Identity\Entity\User;
use App\Identity\Service\AccountAnonymizer;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use DateTimeImmutable;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AdminUserControllerGuardsTest extends TestCase
{
    public function testAnonymizeBlocksSelf(): void
    {
        $admin = (new User())->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        (new ReflectionProperty(User::class, 'id'))->setValue($admin, 1);
        (new ReflectionProperty(User::class, 'uuid'))->setValue($admin, '11111111-1111-7111-8111-111111111111');

        $form = $this->validForm();
        $controller = new ReflectionClass(AdminUserController::class)->newInstanceWithoutConstructor();
        $session = $this->boot($controller, $admin, $form, flash: true);

        $response = $controller->anonymize(Request::create('/x', 'POST'), $admin);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/users', $response->getTargetUrl());
        self::assertSame(['flash.users.cannot_anonymize_self'], $session->getFlashBag()->peek('error'));
    }

    public function testAnonymizeMapsAlreadyAnonymizedFlash(): void
    {
        $admin = (new User())->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        (new ReflectionProperty(User::class, 'id'))->setValue($admin, 1);
        $target = (new User())->setEmail('gone@example.com')->setAnonymizedAt(new DateTimeImmutable('-1 day'));
        (new ReflectionProperty(User::class, 'id'))->setValue($target, 2);
        (new ReflectionProperty(User::class, 'uuid'))->setValue($target, '22222222-2222-7222-8222-222222222222');

        $form = $this->validForm();
        $controller = new ReflectionClass(AdminUserController::class)->newInstanceWithoutConstructor();
        (new ReflectionProperty(AdminUserController::class, 'accountAnonymizer'))->setValue(
            $controller,
            new ReflectionClass(AccountAnonymizer::class)->newInstanceWithoutConstructor(),
        );
        $session = $this->boot($controller, $admin, $form, flash: true);

        $response = $controller->anonymize(Request::create('/x', 'POST'), $target);
        self::assertSame('/admin/users', $response->getTargetUrl());
        self::assertSame(['flash.privacy.already_anonymized'], $session->getFlashBag()->peek('error'));
    }

    public function testChangeRoleBlocksSelf(): void
    {
        $admin = (new User())->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        (new ReflectionProperty(User::class, 'id'))->setValue($admin, 1);
        (new ReflectionProperty(User::class, 'uuid'))->setValue($admin, '11111111-1111-7111-8111-111111111111');

        $form = $this->validForm(['role' => 'user']);
        $controller = new ReflectionClass(AdminUserController::class)->newInstanceWithoutConstructor();
        $session = $this->boot($controller, $admin, $form, flash: true);

        $response = $controller->changeRole(Request::create('/x', 'POST'), $admin);
        self::assertSame('/admin/users', $response->getTargetUrl());
        self::assertSame(['flash.users.cannot_change_own_role'], $session->getFlashBag()->peek('error'));
    }

    public function testChangeRoleRejectsInvalidRole(): void
    {
        $admin = (new User())->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        (new ReflectionProperty(User::class, 'id'))->setValue($admin, 1);
        $target = (new User())->setEmail('member@example.com');
        (new ReflectionProperty(User::class, 'id'))->setValue($target, 2);
        (new ReflectionProperty(User::class, 'uuid'))->setValue($target, '22222222-2222-7222-8222-222222222222');

        $form = $this->validForm(['role' => 'superuser']);
        $controller = new ReflectionClass(AdminUserController::class)->newInstanceWithoutConstructor();
        $session = $this->boot($controller, $admin, $form, flash: true);

        $response = $controller->changeRole(Request::create('/x', 'POST'), $target);
        self::assertSame('/admin/users', $response->getTargetUrl());
        self::assertSame(['flash.users.invalid_role'], $session->getFlashBag()->peek('error'));
    }

    public function testRemoveProject404WhenMembershipMissing(): void
    {
        $admin = (new User())->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $target = (new User())->setEmail('member@example.com');
        $project = (new Project())->setName('Acme')->setSlug('acme');

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn(null);

        $controller = new ReflectionClass(AdminUserController::class)->newInstanceWithoutConstructor();
        (new ReflectionProperty(AdminUserController::class, 'projectMembershipRepository'))->setValue($controller, $memberships);
        $this->boot($controller, $admin, $this->createStub(FormInterface::class));

        $this->expectException(NotFoundHttpException::class);
        $controller->removeProject($target, $project, Request::create('/x', 'POST'));
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function validForm(?array $data = null): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('submit');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        if (null !== $data) {
            $form->method('getData')->willReturn($data);
        }

        return $form;
    }

    private function boot(object $controller, User $user, FormInterface $form, bool $flash = false): Session
    {
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);
        $formFactory->method('createNamed')->willReturn($form);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/users');

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $router);
        if ($flash) {
            $container->set('request_stack', $stack);
        }
        $controller->setContainer($container);

        return $session;
    }
}
