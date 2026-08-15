<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Controller\DashboardAssignmentsController;
use App\Issues\Controller\DashboardNewInReleaseController;
use App\Issues\Repository\IssueSearchRepository;
use App\Issues\Service\DashboardAssignmentsFilterResolver;
use App\Issues\Service\DashboardNewInReleaseFilterResolver;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

final class DashboardIssueInboxesControllerTest extends TestCase
{
    public function testAssignmentsIndexRendersEmptyInbox(): void
    {
        $user = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 4);

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('countAssignments')->willReturn(0);
        $issues->method('searchAssignments')->willReturn([]);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAccessibleByUser')->willReturn([]);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProjects')->willReturn([]);

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamed')->willReturn($form);

        $controller = new DashboardAssignmentsController(
            $issues,
            new DashboardAssignmentsFilterResolver(
                new AccessibleProjectsProvider($projects, new RequestStack()),
                $memberships,
            ),
            new GetFilterFormFactory($formFactory),
        );

        $seen = [];
        $this->boot($controller, $user, $seen, 'dashboard_assignments');

        self::assertSame('ok', $controller->index(Request::create('/dashboard/assignments'))->getContent());
        $ctx = $seen['dashboard/assignments.html.twig'];
        self::assertSame([], $ctx['issues']);
        self::assertSame([], $ctx['projects']);
        self::assertNotEmpty($ctx['scopes']);
    }

    public function testNewInReleaseIndexRendersEmptyList(): void
    {
        $user = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 4);

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('countNewInRelease')->willReturn(0);
        $issues->method('searchNewInRelease')->willReturn([]);
        $issues->method('findDistinctFirstReleasesAcrossProjects')->willReturn([]);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAccessibleByUser')->willReturn([]);

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamed')->willReturn($form);

        $controller = new DashboardNewInReleaseController(
            $issues,
            new DashboardNewInReleaseFilterResolver(
                new AccessibleProjectsProvider($projects, new RequestStack()),
                $issues,
            ),
            new GetFilterFormFactory($formFactory),
        );

        $seen = [];
        $this->boot($controller, $user, $seen, 'dashboard_new_in_release');

        self::assertSame('ok', $controller->index(Request::create('/dashboard/new-in-release'))->getContent());
        $ctx = $seen['dashboard/new_in_release.html.twig'];
        self::assertSame([], $ctx['issues']);
        self::assertSame([], $ctx['releases']);
    }

    /**
     * @param array<string, mixed> $seen
     */
    private function boot(object $controller, User $user, array &$seen, string $route): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/'.$route);
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $name, array $context = []) use (&$seen): string {
                $seen[$name] = $context;

                return 'ok';
            },
        );

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $router);
        $container->set('twig', $twig);
        $controller->setContainer($container);
    }
}
