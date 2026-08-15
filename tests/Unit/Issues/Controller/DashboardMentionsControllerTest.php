<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Controller\DashboardMentionsController;
use App\Issues\Repository\IssueMentionRepository;
use App\Issues\Service\DashboardMentionsFilterResolver;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

final class DashboardMentionsControllerTest extends TestCase
{
    public function testRedirectQueryKeepsNonEmptyFilterFields(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(DashboardMentionsController::class, 'redirectQuery');

        $project = $this->createStub(FormInterface::class);
        $project->method('getData')->willReturn('11111111-1111-4111-8111-111111111111');
        $unread = $this->createStub(FormInterface::class);
        $unread->method('getData')->willReturn('1');
        $perPage = $this->createStub(FormInterface::class);
        $perPage->method('getData')->willReturn('');
        $missing = $this->createStub(FormInterface::class);

        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturnCallback(static fn (string $name): bool => 'project' === $name || 'unread' === $name || 'per_page' === $name);
        $form->method('get')->willReturnCallback(static fn (string $name): FormInterface => match ($name) {
            'project' => $project,
            'unread' => $unread,
            'per_page' => $perPage,
            default => $missing,
        });

        self::assertSame([
            'project' => '11111111-1111-4111-8111-111111111111',
            'unread' => '1',
        ], $method->invoke($controller, $form));
    }

    public function testIndexRendersEmptyInbox(): void
    {
        $user = new User()->setEmail('mentions@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 7);

        $mentions = $this->createStub(IssueMentionRepository::class);
        $mentions->method('countInboxForUser')->willReturn(0);
        $mentions->method('findInboxForUser')->willReturn([]);

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);
        $formFactory->method('createNamed')->willReturn($form);

        $controller = new DashboardMentionsController(
            $mentions,
            $this->createStub(EntityManagerInterface::class),
            new DashboardMentionsFilterResolver($this->accessibleProjects()),
            new GetFilterFormFactory($formFactory),
        );

        $seen = [];
        $this->boot($controller, $user, $formFactory, $seen);

        self::assertSame('ok', $controller->index(Request::create('/dashboard/mentions'))->getContent());
        $ctx = $seen['dashboard/mentions.html.twig'];
        self::assertSame([], $ctx['mentions']);
        self::assertSame(0, $ctx['unread_count']);
        self::assertNull($ctx['markAllReadForm']);
        self::assertSame([], $ctx['markReadForms']);
    }

    public function testMarkRead404WhenMentionMissing(): void
    {
        $user = new User()->setEmail('mentions@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 7);

        $mentions = $this->createStub(IssueMentionRepository::class);
        $mentions->method('findOneForUser')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $controller = new DashboardMentionsController(
            $mentions,
            $this->createStub(EntityManagerInterface::class),
            new DashboardMentionsFilterResolver($this->accessibleProjects()),
            new GetFilterFormFactory($formFactory),
        );
        $seen = [];
        $this->boot($controller, $user, $formFactory, $seen);

        $this->expectException(NotFoundHttpException::class);
        $controller->markRead(Request::create('/read', Request::METHOD_POST), 99);
    }

    private function controller(): DashboardMentionsController
    {
        return new DashboardMentionsController(
            $this->createStub(IssueMentionRepository::class),
            $this->createStub(EntityManagerInterface::class),
            new DashboardMentionsFilterResolver($this->accessibleProjects()),
            new GetFilterFormFactory($this->createStub(FormFactoryInterface::class)),
        );
    }

    private function accessibleProjects(): AccessibleProjectsProvider
    {
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAccessibleByUser')->willReturn([]);

        return new AccessibleProjectsProvider($projects, new RequestStack());
    }

    /**
     * @param array<string, array<string, mixed>> $seen
     */
    private function boot(
        object $controller,
        User $user,
        FormFactoryInterface $formFactory,
        array &$seen,
    ): void {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/dashboard/mentions');
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('twig', $twig);
        $controller->setContainer($container);
    }
}
