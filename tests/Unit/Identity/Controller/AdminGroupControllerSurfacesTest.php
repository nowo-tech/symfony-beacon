<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\AdminIdentityAudit;
use App\Identity\Controller\AdminGroupController;
use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Enum\ProjectRole;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
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

final class AdminGroupControllerSurfacesTest extends TestCase
{
    public function testAuditActionChoicesMapsTranslationKeys(): void
    {
        $controller = new ReflectionClass(AdminGroupController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminGroupController::class, 'auditActionChoices');
        $choices = $method->invoke($controller, AdminIdentityAudit::groupTimelineActions());

        self::assertArrayHasKey('users.activity.action.'.UserActionType::GroupCreated->value, $choices);
        self::assertSame(UserActionType::GroupCreated->value, $choices['users.activity.action.'.UserActionType::GroupCreated->value]);
    }

    public function testAddMemberFlashesWhenUserMissing(): void
    {
        $group = new UserGroup()->setName('Ops')->setSlug('ops');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($group, 3);
        new ReflectionProperty(UserGroup::class, 'uuid')->setValue($group, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['email' => 'missing@example.com']);

        $controller = new ReflectionClass(AdminGroupController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminGroupController::class, 'userRepository')->setValue($controller, $users);
        $session = $this->boot($controller, new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN']), $form, flash: true);

        $response = $controller->addMember($group, Request::create('/x', Request::METHOD_POST));
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/groups/show', $response->getTargetUrl());
        self::assertSame(['flash.groups.user_not_found'], $session->getFlashBag()->peek('error'));
    }

    public function testRemoveMember404WhenMembershipMissing(): void
    {
        $group = new UserGroup()->setName('Ops')->setSlug('ops');
        $member = new User()->setEmail('member@example.com');

        $memberships = $this->createStub(UserGroupMembershipRepository::class);
        $memberships->method('findOneByGroupAndUser')->willReturn(null);

        $controller = new ReflectionClass(AdminGroupController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminGroupController::class, 'groupMembershipRepository')->setValue($controller, $memberships);
        $this->boot($controller, new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN']), $this->createStub(FormInterface::class));

        $this->expectException(NotFoundHttpException::class);
        $controller->removeMember($group, $member, Request::create('/x', Request::METHOD_POST));
    }

    public function testAddMemberFlashesWhenAlreadyMember(): void
    {
        $group = new UserGroup()->setName('Ops')->setSlug('ops');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($group, 3);
        new ReflectionProperty(UserGroup::class, 'uuid')->setValue($group, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $member = new User()->setEmail('member@example.com');
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn($member);

        $memberships = $this->createStub(UserGroupMembershipRepository::class);
        $memberships->method('findOneByGroupAndUser')->willReturn(new UserGroupMembership());

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['email' => 'member@example.com']);

        $controller = new ReflectionClass(AdminGroupController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminGroupController::class, 'userRepository')->setValue($controller, $users);
        new ReflectionProperty(AdminGroupController::class, 'groupMembershipRepository')->setValue($controller, $memberships);
        $session = $this->boot($controller, new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN']), $form, flash: true);

        $response = $controller->addMember($group, Request::create('/x', Request::METHOD_POST));
        self::assertSame('/admin/groups/show', $response->getTargetUrl());
        self::assertSame(['flash.groups.already_member'], $session->getFlashBag()->peek('error'));
    }

    public function testRemoveProject404WhenAccessBelongsToOtherGroup(): void
    {
        $group = new UserGroup()->setName('Ops')->setSlug('ops');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($group, 3);
        $other = new UserGroup()->setName('Other')->setSlug('other');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($other, 9);

        $project = new Project()->setName('Acme')->setSlug('acme');
        $access = new ProjectGroupAccess()->setProject($project)->setUserGroup($other)->setRole(ProjectRole::Member);

        $controller = new ReflectionClass(AdminGroupController::class)->newInstanceWithoutConstructor();
        $this->boot($controller, new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN']), $this->createStub(FormInterface::class));

        $this->expectException(NotFoundHttpException::class);
        $controller->removeProject($group, $access, Request::create('/x', Request::METHOD_POST));
    }

    private function boot(object $controller, User $user, FormInterface $form, bool $flash = false): Session
    {
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route): string => match ($route) {
                'admin_groups_show' => '/admin/groups/show',
                default => '/'.$route,
            },
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('security.token_storage', $tokenStorage);
        if ($flash) {
            $container->set('request_stack', $stack);
        }
        $controller->setContainer($container);

        return $session;
    }
}
