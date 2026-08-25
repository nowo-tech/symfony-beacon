<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Project\Controller\ProjectDangerZoneController;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectHistoryClearer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ProjectDangerZoneControllerTest extends TestCase
{
    public function testClearHistoryClearsTelemetryAndRedirects(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 7);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, '11111111-1111-7111-8111-111111111111');

        $user = new User()->setEmail('owner@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 3);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::atLeastOnce())->method('executeStatement');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->expects(self::once())->method('clear');
        $em->method('find')->willReturn($user);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $controller = new ProjectDangerZoneController(
            $em,
            new ProjectHistoryClearer($em),
            $this->createStub(ProjectRepository::class),
            new UserActionRecorder($em, new RequestStack()),
        );

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/settings/danger');

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/projects/x/clear-history', Request::METHOD_POST);
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('request_stack', $stack);
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        $response = $controller->clearHistory($project, $request);
        self::assertTrue($response->isRedirection());
        self::assertSame('/settings/danger', $response->headers->get('Location'));
        self::assertSame(['flash.project.history_cleared'], $session->getFlashBag()->peek('success'));
    }
}
