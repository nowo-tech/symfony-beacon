<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics\Controller;

use App\Analytics\Controller\AnalyticsController;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Analytics\Service\AnalyticsPeriodResolver;
use App\Analytics\Service\AnalyticsSeriesService;
use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Shared\Form\GetFilterFormFactory;
use App\Tests\Support\ProjectAccessServiceFactory;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final class AnalyticsControllerTest extends TestCase
{
    public function testShowRendersEmptySeriesAndRecordsAction(): void
    {
        $user = new User()->setEmail('analyst@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($user, 2);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, '11111111-1111-7111-8111-111111111111');

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $access = ProjectAccessServiceFactory::create(
            $this->createStub(ProjectMembershipRepository::class),
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );

        $stats = $this->createStub(DailyProjectStatRepository::class);
        $stats->method('findInRange')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamed')->willReturn($form);

        $controller = new AnalyticsController(
            new AnalyticsPeriodResolver(),
            new AnalyticsSeriesService($stats, $this->createStub(EventRepository::class)),
            $access,
            new UserActionRecorder($em, new RequestStack()),
            new GetFilterFormFactory($formFactory),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/analytics');
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->willReturnCallback(
            static function (string $template, array $context) use ($project): string {
                self::assertSame('analytics/show.html.twig', $template);
                self::assertSame($project, $context['project']);
                self::assertNotEmpty($context['stats']);
                self::assertFalse($context['has_volume']);
                self::assertSame('30', $context['period']);

                return 'analytics';
            },
        );

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $router);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        self::assertSame(
            'analytics',
            $controller->show($project, Request::create('/projects/x/analytics'))->getContent(),
        );
    }
}
