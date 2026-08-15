<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Controller;

use App\Identity\Entity\User;
use App\Notifications\Controller\DashboardAlertsController;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Service\DashboardAlertsFilterResolver;
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

final class DashboardAlertsControllerTest extends TestCase
{
    public function testIndexRendersEmptyFailedDeliveries(): void
    {
        $user = new User()->setEmail('ops@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 3);

        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('countWithFailedLastDeliveryInProjects')->willReturn(0);
        $destinations->method('findWithFailedLastDeliveryInProjects')->willReturn([]);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAccessibleByUser')->willReturn([]);

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamed')->willReturn($form);

        $controller = new DashboardAlertsController(
            $destinations,
            new DashboardAlertsFilterResolver(new AccessibleProjectsProvider($projects, new RequestStack())),
            new GetFilterFormFactory($formFactory),
        );

        $seen = [];
        $this->boot($controller, $user, $seen);

        self::assertSame('ok', $controller->index(Request::create('/dashboard/alerts'))->getContent());
        $ctx = $seen['dashboard/alerts.html.twig'];
        self::assertSame([], $ctx['destinations']);
        self::assertSame([], $ctx['projects']);
        self::assertSame(1, $ctx['pagination']['page']);
    }

    /**
     * @param array<string, mixed> $seen
     */
    private function boot(DashboardAlertsController $controller, User $user, array &$seen): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/dashboard/alerts');
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
