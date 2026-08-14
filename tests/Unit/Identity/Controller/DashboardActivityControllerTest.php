<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\DashboardActivityController;
use App\Identity\Entity\User;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Service\DashboardActivityFilterResolver;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use App\Shared\Form\GetFilterFormFactory;
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

final class DashboardActivityControllerTest extends TestCase
{
    public function testIndexRendersEmptyActivity(): void
    {
        $user = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 8);

        $actions = $this->createStub(UserActionRepository::class);
        $actions->method('countActorProductActivity')->willReturn(0);
        $actions->method('findActorProductActivity')->willReturn([]);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAccessibleByUser')->willReturn([]);

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamed')->willReturn($form);

        $controller = new DashboardActivityController(
            $actions,
            new DashboardActivityFilterResolver(new AccessibleProjectsProvider($projects, new RequestStack())),
            new GetFilterFormFactory($formFactory),
        );

        $seen = [];
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/dashboard/activity');
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

        self::assertSame('ok', $controller->index(Request::create('/dashboard/activity'))->getContent());
        self::assertSame([], $seen['dashboard/activity.html.twig']['actions']);
        self::assertSame([], $seen['dashboard/activity.html.twig']['projects']);
    }
}
