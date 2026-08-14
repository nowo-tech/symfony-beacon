<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Controller\IssueController;
use App\Issues\Entity\IssueSavedView;
use App\Issues\Repository\IssueSavedViewRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class IssueControllerSavedViewsTest extends TestCase
{
    public function testApplyView404WhenMissing(): void
    {
        $user = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member);

        $views = $this->createStub(IssueSavedViewRepository::class);
        $views->method('findOneForUserAndProject')->willReturn(null);

        $controller = $this->controller($user, $membership, $views);
        $this->expectException(NotFoundHttpException::class);
        $controller->applyView($project, '11111111-1111-7111-8111-111111111111');
    }

    public function testApplyViewRedirectsWithScalarQueryKeys(): void
    {
        $user = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member);

        $view = new IssueSavedView();
        $view->setUser($user);
        $view->setProject($project);
        $view->setName('Errors');
        $view->setQueryJson([
            'q' => 'boom',
            'level' => 'error',
            'status' => '',
            'unknown' => 'drop',
            'nested' => ['x' => 1],
            'per_page' => 50,
        ]);

        $views = $this->createStub(IssueSavedViewRepository::class);
        $views->method('findOneForUserAndProject')->willReturn($view);

        $controller = $this->controller($user, $membership, $views);
        $response = $controller->applyView($project, '11111111-1111-7111-8111-111111111111');
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/issues?q=boom&level=error&per_page=50', $response->getTargetUrl());
    }

    public function testDeleteViewInvalidCsrfFlashesError(): void
    {
        $user = new User()->setEmail('dev@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Admin);

        $form = $this->createStub(FormInterface::class);
        $form->method('submit');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $session = new Session(new MockArraySessionStorage());
        $controller = $this->controller($user, $membership, $this->createStub(IssueSavedViewRepository::class), $form, $session);

        $response = $controller->deleteView(
            Request::create('/x', Request::METHOD_POST),
            $project,
            '11111111-1111-7111-8111-111111111111',
        );
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/issues', $response->getTargetUrl());
        self::assertSame(['issues.view_invalid'], $session->getFlashBag()->peek('error'));
    }

    private function controller(
        User $user,
        ProjectMembership $membership,
        IssueSavedViewRepository $views,
        ?FormInterface $form = null,
        ?Session $session = null,
    ): IssueController {
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);

        $controller = new ReflectionClass(IssueController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(IssueController::class, 'projectAccess')->setValue(
            $controller,
            new ProjectAccessService(
                $memberships,
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $auth,
                new RequestStack(),
            ),
        );
        new ReflectionProperty(IssueController::class, 'savedViewRepository')->setValue($controller, $views);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $route, array $params = []): string {
                unset($params['id']);
                $query = http_build_query($params);

                return '/issues'.('' !== $query ? '?'.$query : '');
            },
        );
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $router);
        if ($form instanceof FormInterface) {
            $formFactory = $this->createStub(FormFactoryInterface::class);
            $formFactory->method('create')->willReturn($form);
            $container->set('form.factory', $formFactory);
        }
        if ($session instanceof Session) {
            $request = Request::create('/');
            $request->setSession($session);
            $stack = new RequestStack();
            $stack->push($request);
            $container->set('request_stack', $stack);
        }
        $controller->setContainer($container);

        return $controller;
    }
}
