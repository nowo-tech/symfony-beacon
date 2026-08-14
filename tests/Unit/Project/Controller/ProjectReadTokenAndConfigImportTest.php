<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Project\Controller\ProjectConfigController;
use App\Project\Controller\ProjectReadTokenController;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectReadTokenRepository;
use App\Project\Service\ProjectConfigPortability;
use App\Project\Service\ProjectReadTokenManager;
use Doctrine\ORM\EntityManagerInterface;
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

final class ProjectReadTokenAndConfigImportTest extends TestCase
{
    public function testReadTokenRevokeThrowsWhenTokenMissing(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, '11111111-1111-7111-8111-111111111111');
        $user = new User()->setEmail('owner@example.com');

        $tokens = $this->createStub(ProjectReadTokenRepository::class);
        $tokens->method('findOneBy')->willReturn(null);

        $controller = new ProjectReadTokenController(
            new ReflectionClass(ProjectReadTokenManager::class)->newInstanceWithoutConstructor(),
            $tokens,
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('submit');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $this->bootController($controller, $user, $form, flash: false);

        $this->expectException(NotFoundHttpException::class);
        $controller->revoke(Request::create('/x', Request::METHOD_POST), $project, '22222222-2222-7222-8222-222222222222');
    }

    public function testReadTokenRevokeInvalidCsrfRedirects(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, '11111111-1111-7111-8111-111111111111');
        $user = new User()->setEmail('owner@example.com');

        $controller = new ProjectReadTokenController(
            new ReflectionClass(ProjectReadTokenManager::class)->newInstanceWithoutConstructor(),
            $this->createStub(ProjectReadTokenRepository::class),
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('submit');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $this->bootController($controller, $user, $form, flash: true);

        $response = $controller->revoke(Request::create('/x', Request::METHOD_POST), $project, '22222222-2222-7222-8222-222222222222');
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/settings/access', $response->getTargetUrl());
    }

    public function testReadTokenCreateInvalidCsrfRedirects(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, '11111111-1111-7111-8111-111111111111');
        $user = new User()->setEmail('owner@example.com');

        $controller = new ProjectReadTokenController(
            new ReflectionClass(ProjectReadTokenManager::class)->newInstanceWithoutConstructor(),
            $this->createStub(ProjectReadTokenRepository::class),
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $this->bootController($controller, $user, $form, flash: true);

        $response = $controller->create(Request::create('/x', Request::METHOD_POST), $project);
        self::assertSame('/settings/access', $response->getTargetUrl());
    }

    public function testConfigImportInvalidCsrfRedirects(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');
        $user = new User()->setEmail('owner@example.com');

        $controller = new ProjectConfigController(
            new ReflectionClass(ProjectConfigPortability::class)->newInstanceWithoutConstructor(),
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $this->bootController($controller, $user, $form, flash: true, settingsPath: '/settings/data');

        $response = $controller->import($project, Request::create('/x', Request::METHOD_POST));
        self::assertSame('/settings/data', $response->getTargetUrl());
    }

    public function testConfigImportMissingFileMapsFlash(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');
        $user = new User()->setEmail('owner@example.com');

        $controller = new ProjectConfigController(
            new ReflectionClass(ProjectConfigPortability::class)->newInstanceWithoutConstructor(),
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
        );

        $bundle = $this->createStub(FormInterface::class);
        $bundle->method('getData')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->willReturn($bundle);

        $session = new Session(new MockArraySessionStorage());
        $this->bootController($controller, $user, $form, flash: true, settingsPath: '/settings/data', session: $session);

        $response = $controller->import($project, Request::create('/x', Request::METHOD_POST));
        self::assertSame('/settings/data', $response->getTargetUrl());
        self::assertSame(['flash.project.config_missing_file'], $session->getFlashBag()->peek('error'));
    }

    /** @param FormInterface<mixed> $form */
    private function bootController(
        object $controller,
        User $user,
        FormInterface $form,
        bool $flash,
        string $settingsPath = '/settings/access',
        ?Session $session = null,
    ): void {
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string => match ($route) {
                'project_settings_section' => $settingsPath,
                default => '/'.$route,
            },
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('security.token_storage', $tokenStorage);

        if ($flash) {
            $request = Request::create('/');
            $request->setSession($session ?? new Session(new MockArraySessionStorage()));
            $stack = new RequestStack();
            $stack->push($request);
            $container->set('request_stack', $stack);
        }

        $controller->setContainer($container);
    }
}
