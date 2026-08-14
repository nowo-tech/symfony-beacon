<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Project\Access\ProjectAccess;
use App\Project\Controller\ProjectController;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Enum\ProjectSettingsSection;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectControllerRedirectsTest extends TestCase
{
    public function testShowRedirectsToIssueIndex(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $controller = new ReflectionClass(ProjectController::class)->newInstanceWithoutConstructor();
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []): string => '/'.$route.'/'.($params['id'] ?? ''),
        );
        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        $response = $controller->show($project);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/issue_index/aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa', $response->getTargetUrl());
    }

    public function testSettingsIndexRedirectsToDefaultSection(): void
    {
        $user = new User()->setEmail('owner@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Owner);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        $controller = new ReflectionClass(ProjectController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(ProjectController::class, 'projectAccess')->setValue(
            $controller,
            new ProjectAccessService(
                $memberships,
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $auth,
                new RequestStack(),
            ),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []): string => '/settings/'.($params['section'] ?? ''),
        );
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $router);
        $controller->setContainer($container);

        $response = $controller->settingsIndex($project);
        self::assertSame('/settings/'.ProjectSettingsSection::defaultFor(
            new ProjectAccess(ProjectRole::Owner, viaGroup: false),
        )->value, $response->getTargetUrl());
    }

    public function testSettingsRejectsUnknownSection(): void
    {
        $user = new User()->setEmail('owner@example.com');
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Owner);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);

        $controller = new ReflectionClass(ProjectController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(ProjectController::class, 'projectAccess')->setValue(
            $controller,
            new ProjectAccessService(
                $memberships,
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $this->createStub(AuthorizationCheckerInterface::class),
                new RequestStack(),
            ),
        );
        new ReflectionProperty(ProjectController::class, 'userActionRecorder')->setValue(
            $controller,
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        $this->expectException(NotFoundHttpException::class);
        $controller->settings($project, Request::create('/settings/nope'), 'nope');
    }
}
