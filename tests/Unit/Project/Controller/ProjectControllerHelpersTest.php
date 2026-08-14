<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Identity\Entity\User;
use App\Issues\Repository\EventRepository;
use App\Project\Access\ProjectAccess;
use App\Project\Controller\AdminProjectAccessController;
use App\Project\Controller\ProjectController;
use App\Project\Controller\ProjectMemberController;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectGovernanceResolver;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectControllerHelpersTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testRequireDirectMembershipReturnsRowOr404(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        $user = new User()->setEmail('member@example.com');
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member);

        $repo = $this->createStub(ProjectMembershipRepository::class);
        $repo->method('findOneByProjectAndUser')->willReturnCallback(
            static fn (Project $p, User $u): ?ProjectMembership => $p === $project && $u === $user ? $membership : null,
        );

        $controller = new ReflectionClass(AdminProjectAccessController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminProjectAccessController::class, 'membershipRepository')->setValue($controller, $repo);

        $method = new ReflectionMethod(AdminProjectAccessController::class, 'requireDirectMembership');
        self::assertSame($membership, $method->invoke($controller, $project, $user));

        $this->expectException(NotFoundHttpException::class);
        $method->invoke($controller, $project, new User()->setEmail('missing@example.com'));
    }

    public function testRequireTargetMembershipReturnsRowOr404(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        $user = new User()->setEmail('member@example.com');
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member);

        $repo = $this->createStub(ProjectMembershipRepository::class);
        $repo->method('findOneByProjectAndUser')->willReturnCallback(
            static fn (Project $p, User $u): ?ProjectMembership => $p === $project && $u === $user ? $membership : null,
        );

        $access = new ProjectAccessService(
            $repo,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $this->createStub(AuthorizationCheckerInterface::class),
            new RequestStack(),
        );

        $controller = new ReflectionClass(ProjectMemberController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(ProjectMemberController::class, 'projectAccess')->setValue($controller, $access);

        $method = new ReflectionMethod(ProjectMemberController::class, 'requireTargetMembership');
        self::assertSame($membership, $method->invoke($controller, $project, $user));

        $this->expectException(NotFoundHttpException::class);
        $method->invoke($controller, $project, new User()->setEmail('missing@example.com'));
    }

    public function testRedirectAfterMemberAlertsSaveHonorsReturnQuery(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, '11111111-1111-7111-8111-111111111111');

        $controller = new ReflectionClass(ProjectController::class)->newInstanceWithoutConstructor();
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $route, array $params = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string {
                unset($referenceType);
                $fragment = isset($params['_fragment']) ? (string) $params['_fragment'] : '';
                unset($params['_fragment']);
                $url = match ($route) {
                    'account_display_notifications' => '/account/notifications',
                    'project_settings_section' => '/projects/'.$params['id'].'/settings/'.$params['section'],
                    default => '/'.$route,
                };

                return '' !== $fragment ? $url.'#'.$fragment : $url;
            },
        );
        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        $method = new ReflectionMethod(ProjectController::class, 'redirectAfterMemberAlertsSave');

        $toAccount = $method->invoke($controller, $project, Request::create('/x', Request::METHOD_GET, ['return' => 'account']));
        self::assertInstanceOf(RedirectResponse::class, $toAccount);
        self::assertSame('/account/notifications', $toAccount->getTargetUrl());

        $toSettings = $method->invoke($controller, $project, Request::create('/x'));
        self::assertSame(
            '/projects/11111111-1111-7111-8111-111111111111/settings/alerts#member-alerts',
            $toSettings->getTargetUrl(),
        );
    }

    public function testMaybeFlashApproachingQuotaWarnsOncePerSession(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme')->setEventQuotaDaily(10)->setEventQuotaMonthly(100);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(8);
        $events->method('countReceivedSinceForProject')->willReturn(90);

        $controller = new ReflectionClass(ProjectController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(ProjectController::class, 'governanceResolver')->setValue(
            $controller,
            new ProjectGovernanceResolver(
                $events,
                $this->opsDefaultsWith(static function (): void {
                }),
            ),
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = new Container();
        $container->set('request_stack', $stack);
        $controller->setContainer($container);

        $method = new ReflectionMethod(ProjectController::class, 'maybeFlashApproachingQuota');
        $method->invoke($controller, $request, $project, new ProjectAccess(ProjectRole::Owner));
        $method->invoke($controller, $request, $project, new ProjectAccess(ProjectRole::Owner));

        $flashes = $session->getFlashBag()->peekAll();
        self::assertSame(
            ['flash.project.quota_approaching', 'flash.project.quota_monthly_approaching'],
            $flashes['warning'] ?? [],
        );
    }

    public function testMaybeFlashApproachingQuotaSkipsWithoutSettingsPermission(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme')->setEventQuotaDaily(10);
        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(9);

        $controller = new ReflectionClass(ProjectController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(ProjectController::class, 'governanceResolver')->setValue(
            $controller,
            new ProjectGovernanceResolver(
                $events,
                $this->opsDefaultsWith(static function (): void {
                }),
            ),
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);
        $container = new Container();
        $container->set('request_stack', $stack);
        $controller->setContainer($container);

        $method = new ReflectionMethod(ProjectController::class, 'maybeFlashApproachingQuota');
        $method->invoke($controller, $request, $project, new ProjectAccess(ProjectRole::Viewer));

        self::assertSame([], $session->getFlashBag()->peekAll());
    }
}
