<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Repository\IssueRepository;
use App\Project\Controller\AdminProjectAccessController;
use App\Project\Controller\ProjectMemberController;
use App\Project\Controller\ProjectShareLinkController;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Entity\ProjectShareLink;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectGroupAccessManager;
use App\Project\Service\ProjectMembershipFormSupport;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectMembershipPolicy;
use App\Project\Service\ProjectShareLinkManager;
use App\Tests\Support\ProjectAccessServiceFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectAccessMutationControllersTest extends TestCase
{
    public function testShareLinkRevokeInvalidCsrfAndMissingLink(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, '11111111-1111-7111-8111-111111111111');
        $user = new User()->setEmail('owner@example.com');

        $links = $this->createStub(ProjectShareLinkRepository::class);
        $links->method('findOneBy')->willReturn(null);

        $controller = new ProjectShareLinkController(
            new ReflectionClass(ProjectShareLinkManager::class)->newInstanceWithoutConstructor(),
            $links,
            $this->createStub(IssueRepository::class),
        );

        $invalid = $this->createStub(FormInterface::class);
        $invalid->method('submit');
        $invalid->method('isSubmitted')->willReturn(false);
        $invalid->method('isValid')->willReturn(false);
        $session = $this->boot($controller, $user, $invalid, flash: true);

        $response = $controller->revoke(Request::create('/x', Request::METHOD_POST), $project, '22222222-2222-7222-8222-222222222222');
        self::assertTrue($response->isRedirection());
        self::assertSame('/settings/access', $response->headers->get('Location'));
        self::assertSame(['projects.share.invalid_csrf'], $session->getFlashBag()->peek('error'));

        $valid = $this->createStub(FormInterface::class);
        $valid->method('submit');
        $valid->method('isSubmitted')->willReturn(true);
        $valid->method('isValid')->willReturn(true);
        $this->boot($controller, $user, $valid, flash: false);

        $this->expectException(NotFoundHttpException::class);
        $controller->revoke(Request::create('/x', Request::METHOD_POST), $project, '22222222-2222-7222-8222-222222222222');
    }

    public function testShareLinkRevokeSuccess(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, '11111111-1111-7111-8111-111111111111');
        $actor = new User()->setEmail('owner@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($actor, 2);

        $link = new ProjectShareLink()
            ->setProject($project)
            ->setTokenHash(hash('sha256', 'token'))
            ->setExpiresAt(new DateTimeImmutable('+1 day'));

        $links = $this->createStub(ProjectShareLinkRepository::class);
        $links->method('findOneBy')->willReturn($link);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $access = ProjectAccessServiceFactory::create(
            $this->createStub(ProjectMembershipRepository::class),
            $this->createStub(ProjectGroupAccessRepository::class),
            $links,
            $auth,
            new RequestStack(),
        );

        $controller = new ProjectShareLinkController(
            new ProjectShareLinkManager(
                $em,
                $links,
                $access,
                new UserActionRecorder($em, new RequestStack()),
            ),
            $links,
            $this->createStub(IssueRepository::class),
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('submit');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $session = $this->boot($controller, $actor, $form, flash: true);

        $response = $controller->revoke(Request::create('/x', Request::METHOD_POST), $project, $link->getUuid());
        self::assertSame('/settings/access', $response->headers->get('Location'));
        self::assertSame(['projects.share.revoked'], $session->getFlashBag()->peek('success'));
        self::assertTrue($link->isRevoked());
    }

    public function testMemberSetActiveDeactivatesMembership(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $actor = new User()->setEmail('owner@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($actor, 1);
        $member = new User()->setEmail('member@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($member, 2);

        $membership = new ProjectMembership()
            ->setProject($project)
            ->setUser($member)
            ->setRole(ProjectRole::Member)
            ->setActive(true);
        new ReflectionProperty(ProjectMembership::class, 'id')->setValue($membership, 9);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::once())->method('flush');

        $access = ProjectAccessServiceFactory::create(
            $memberships,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
        $policy = new ProjectMembershipPolicy(
            $memberships,
            $this->createStub(UserGroupMembershipRepository::class),
            $access,
            $auth,
        );
        $manager = new ProjectMembershipManager(
            $this->createStub(UserRepository::class),
            $memberships,
            $policy,
            new UserActionRecorder($em, new RequestStack()),
            $em,
        );

        $controller = new ProjectMemberController(
            $access,
            $manager,
            new ReflectionClass(ProjectGroupAccessManager::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ProjectMembershipFormSupport::class)->newInstanceWithoutConstructor(),
            $this->createStub(UserGroupRepository::class),
            $this->createStub(UserRepository::class),
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['active' => '0']);
        $session = $this->boot($controller, $actor, $form, flash: true, settingsPath: '/settings/access');

        $response = $controller->setActive($project, $member, Request::create('/x', Request::METHOD_POST));
        self::assertSame('/settings/access', $response->headers->get('Location'));
        self::assertFalse($membership->isActive());
        self::assertSame(['flash.project.member_deactivated'], $session->getFlashBag()->peek('success'));
    }

    public function testMemberChangeRoleFlashesWhenRoleInvalid(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $actor = new User()->setEmail('owner@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($actor, 1);
        $member = new User()->setEmail('member@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($member, 2);

        $membership = new ProjectMembership()
            ->setProject($project)
            ->setUser($member)
            ->setRole(ProjectRole::Member);
        new ReflectionProperty(ProjectMembership::class, 'id')->setValue($membership, 9);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $access = ProjectAccessServiceFactory::create(
            $memberships,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
        $policy = new ProjectMembershipPolicy(
            $memberships,
            $this->createStub(UserGroupMembershipRepository::class),
            $access,
            $auth,
        );
        $manager = new ProjectMembershipManager(
            $this->createStub(UserRepository::class),
            $memberships,
            $policy,
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
            $this->createStub(EntityManagerInterface::class),
        );

        $controller = new ProjectMemberController(
            $access,
            $manager,
            new ReflectionClass(ProjectGroupAccessManager::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ProjectMembershipFormSupport::class)->newInstanceWithoutConstructor(),
            $this->createStub(UserGroupRepository::class),
            $this->createStub(UserRepository::class),
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['role' => 'not-a-role']);
        $session = $this->boot($controller, $actor, $form, flash: true);

        $response = $controller->changeRole($project, $member, Request::create('/x', Request::METHOD_POST));
        self::assertSame('/settings/access', $response->headers->get('Location'));
        self::assertSame(['flash.project.member_invalid_role'], $session->getFlashBag()->peek('error'));
    }

    public function testAdminChangeMemberRole404WhenMembershipMissing(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        $member = new User()->setEmail('missing@example.com');

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn(null);

        $controller = new AdminProjectAccessController(
            new ReflectionClass(ProjectMembershipManager::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ProjectGroupAccessManager::class)->newInstanceWithoutConstructor(),
            $memberships,
            new ReflectionClass(ProjectMembershipFormSupport::class)->newInstanceWithoutConstructor(),
            $this->createStub(UserGroupRepository::class),
            new CsrfOnlyFormFactory($this->createStub(FormFactoryInterface::class)),
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->changeMemberRole($project, $member, Request::create('/x', Request::METHOD_POST));
    }

    public function testMemberRemove404WhenMembershipMissing(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        $actor = new User()->setEmail('owner@example.com');
        $member = new User()->setEmail('missing@example.com');

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn(null);
        $access = ProjectAccessServiceFactory::create(
            $memberships,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $this->createStub(AuthorizationCheckerInterface::class),
            new RequestStack(),
        );

        $controller = new ProjectMemberController(
            $access,
            new ReflectionClass(ProjectMembershipManager::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ProjectGroupAccessManager::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ProjectMembershipFormSupport::class)->newInstanceWithoutConstructor(),
            $this->createStub(UserGroupRepository::class),
            $this->createStub(UserRepository::class),
        );
        $this->boot($controller, $actor, $this->createStub(FormInterface::class), flash: false);

        $this->expectException(NotFoundHttpException::class);
        $controller->remove($project, $member, Request::create('/x', Request::METHOD_POST));
    }

    public function testAdminRemoveMember404WhenMembershipMissing(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        $member = new User()->setEmail('missing@example.com');

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn(null);

        $controller = new AdminProjectAccessController(
            new ReflectionClass(ProjectMembershipManager::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ProjectGroupAccessManager::class)->newInstanceWithoutConstructor(),
            $memberships,
            new ReflectionClass(ProjectMembershipFormSupport::class)->newInstanceWithoutConstructor(),
            $this->createStub(UserGroupRepository::class),
            new CsrfOnlyFormFactory($this->createStub(FormFactoryInterface::class)),
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->removeMember($project, $member, Request::create('/x', Request::METHOD_POST));
    }

    public function testAdminGroupMutations404WhenGroupBelongsToOtherProject(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        $other = new Project()->setName('Other')->setSlug('other');
        new ReflectionProperty(Project::class, 'id')->setValue($other, 2);

        $groupAccess = new ProjectGroupAccess()->setProject($other)->setRole(ProjectRole::Member);
        new ReflectionProperty(ProjectGroupAccess::class, 'id')->setValue($groupAccess, 7);

        $controller = new AdminProjectAccessController(
            new ReflectionClass(ProjectMembershipManager::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ProjectGroupAccessManager::class)->newInstanceWithoutConstructor(),
            $this->createStub(ProjectMembershipRepository::class),
            new ReflectionClass(ProjectMembershipFormSupport::class)->newInstanceWithoutConstructor(),
            $this->createStub(UserGroupRepository::class),
            new CsrfOnlyFormFactory($this->createStub(FormFactoryInterface::class)),
        );

        try {
            $controller->changeGroupRole($project, $groupAccess, Request::create('/x', Request::METHOD_POST));
            self::fail('Expected NotFoundHttpException');
        } catch (NotFoundHttpException) {
        }

        $this->expectException(NotFoundHttpException::class);
        $controller->removeGroup($project, $groupAccess, Request::create('/x', Request::METHOD_POST));
    }

    public function testAdminAddGroupFlashWhenGroupMissing(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $actor = new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($actor, 1);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('hydrateAccessGraph');
        $groups = $this->createStub(UserGroupRepository::class);
        $groups->method('findAllOrdered')->willReturn([]);
        $groups->method('findOneBy')->willReturn(null);

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $access = ProjectAccessServiceFactory::create(
            $memberships,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
        $policy = new ProjectMembershipPolicy(
            $memberships,
            $this->createStub(UserGroupMembershipRepository::class),
            $access,
            $auth,
        );
        $groupManager = new ProjectGroupAccessManager(
            $this->createStub(ProjectGroupAccessRepository::class),
            $policy,
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
            $this->createStub(EntityManagerInterface::class),
        );

        $controller = new AdminProjectAccessController(
            new ReflectionClass(ProjectMembershipManager::class)->newInstanceWithoutConstructor(),
            $groupManager,
            $memberships,
            new ProjectMembershipFormSupport($projects, $groups),
            $groups,
            new CsrfOnlyFormFactory($this->createStub(FormFactoryInterface::class)),
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['group' => 'missing-uuid', 'role' => 'member']);
        $session = $this->boot($controller, $actor, $form, flash: true, settingsPath: '/admin/projects/show');

        $response = $controller->addGroup($project, Request::create('/x', Request::METHOD_POST));
        self::assertSame('/admin/projects/show', $response->headers->get('Location'));
        self::assertSame(['flash.project.group_not_found'], $session->getFlashBag()->peek('error'));
    }

    /** @param FormInterface<mixed> $form */
    private function boot(
        object $controller,
        User $user,
        FormInterface $form,
        bool $flash,
        string $settingsPath = '/settings/access',
    ): Session {
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route): string => match ($route) {
                'project_settings_section' => $settingsPath,
                'admin_projects_show' => '/admin/projects/show',
                default => '/'.$route,
            },
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

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
