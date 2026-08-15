<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Identity\Controller\DashboardActivityController;
use App\Identity\Entity\User;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Service\DashboardActivityFilterResolver;
use App\Issues\Controller\DashboardAssignmentsController;
use App\Issues\Controller\DashboardNewInReleaseController;
use App\Issues\Repository\IssueSearchRepository;
use App\Issues\Service\DashboardAssignmentsFilterResolver;
use App\Issues\Service\DashboardNewInReleaseFilterResolver;
use App\Notifications\Controller\DashboardAlertsController;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Service\DashboardAlertsFilterResolver;
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

final class DashboardSectionControllersTest extends TestCase
{
    public function testActivityIndexRendersWithEmptyActions(): void
    {
        $user = $this->user();
        $actions = $this->createStub(UserActionRepository::class);
        $actions->method('countActorProductActivity')->willReturn(0);
        $actions->method('findActorProductActivity')->willReturn([]);

        $controller = new DashboardActivityController(
            $actions,
            new DashboardActivityFilterResolver($this->accessibleProjects()),
            $this->filterForms(),
        );
        $this->boot($controller, $user, 'dashboard/activity.html.twig');

        $response = $controller->index(Request::create('/dashboard/activity'));
        self::assertSame('ok', $response->getContent());
    }

    public function testAssignmentsIndexRendersWithEmptyIssues(): void
    {
        $user = $this->user();
        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('countAssignments')->willReturn(0);
        $issues->method('searchAssignments')->willReturn([]);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProjects')->willReturn([$user]);

        $controller = new DashboardAssignmentsController(
            $issues,
            new DashboardAssignmentsFilterResolver($this->accessibleProjects(), $memberships),
            $this->filterForms(),
        );
        $this->boot($controller, $user, 'dashboard/assignments.html.twig');

        self::assertSame('ok', $controller->index(Request::create('/dashboard/assignments'))->getContent());
    }

    public function testAlertsIndexRendersWithEmptyDestinations(): void
    {
        $user = $this->user();
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('countWithFailedLastDeliveryInProjects')->willReturn(0);
        $destinations->method('findWithFailedLastDeliveryInProjects')->willReturn([]);

        $controller = new DashboardAlertsController(
            $destinations,
            new DashboardAlertsFilterResolver($this->accessibleProjects()),
            $this->filterForms(),
        );
        $this->boot($controller, $user, 'dashboard/alerts.html.twig');

        self::assertSame('ok', $controller->index(Request::create('/dashboard/alerts'))->getContent());
    }

    public function testNewInReleaseIndexRendersWithEmptyIssues(): void
    {
        $user = $this->user();
        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('findDistinctFirstReleasesAcrossProjects')->willReturn([]);
        $issues->method('countNewInRelease')->willReturn(0);
        $issues->method('searchNewInRelease')->willReturn([]);

        $controller = new DashboardNewInReleaseController(
            $issues,
            new DashboardNewInReleaseFilterResolver($this->accessibleProjects(), $issues),
            $this->filterForms(),
        );
        $this->boot($controller, $user, 'dashboard/new_in_release.html.twig');

        self::assertSame('ok', $controller->index(Request::create('/dashboard/new-in-release'))->getContent());
    }

    private function accessibleProjects(): AccessibleProjectsProvider
    {
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAccessibleByUser')->willReturn([]);

        return new AccessibleProjectsProvider($projects, new RequestStack());
    }

    private function filterForms(): GetFilterFormFactory
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $factory = $this->createStub(FormFactoryInterface::class);
        $factory->method('createNamed')->willReturn($form);

        return new GetFilterFormFactory($factory);
    }

    private function boot(object $controller, User $user, string $expectedTemplate): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/dashboard');

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->willReturnCallback(
            static function (string $template) use ($expectedTemplate): string {
                self::assertSame($expectedTemplate, $template);

                return 'ok';
            },
        );

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $router);
        $container->set('twig', $twig);
        $controller->setContainer($container);
    }

    private function user(): User
    {
        $user = new User()->setEmail('dash@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 11);

        return $user;
    }
}
