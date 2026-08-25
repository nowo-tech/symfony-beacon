<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Project\Controller\ProjectApiKeyController;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectApiKeyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ProjectApiKeyControllerTest extends TestCase
{
    public function testAssertKeyBelongsToProjectAndSettingsBaseUrl(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $controller = new ProjectApiKeyController(
            $em,
            new ProjectApiKeyFactory($em),
            new HumanFriendlyTokenGenerator(),
            new UserActionRecorder($em, new RequestStack()),
        );

        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $other = new Project()->setName('Other')->setSlug('other');
        new ReflectionProperty(Project::class, 'id')->setValue($other, 6);

        $key = new ProjectApiKey();
        $key->setProject($project);

        $assert = new ReflectionMethod(ProjectApiKeyController::class, 'assertKeyBelongsToProject');
        $assert->invoke($controller, $key, $project);

        $baseUrl = new ReflectionMethod(ProjectApiKeyController::class, 'settingsBaseUrl');
        self::assertSame(
            'https://beacon.test',
            $baseUrl->invoke($controller, Request::create('https://beacon.test/projects/x/keys')),
        );

        $this->expectException(NotFoundHttpException::class);
        $assert->invoke($controller, $key, $other);
    }

    public function testCreateKeyGeneratesLabelWhenEmpty(): void
    {
        $user = new User()->setEmail('owner@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 2);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $keysRepo = $this->createStub(EntityRepository::class);
        $keysRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($keysRepo);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::once())->method('flush');

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['label' => '   ']);

        $controller = new ProjectApiKeyController(
            $em,
            new ProjectApiKeyFactory($em),
            new HumanFriendlyTokenGenerator(),
            new UserActionRecorder($em, new RequestStack()),
        );
        $session = $this->boot($controller, $user, $form, flash: true);

        $request = Request::create('https://beacon.test/projects/x/keys', Request::METHOD_POST);
        $request->setSession($session);

        $response = $controller->createKey($project, $request);
        self::assertTrue($response->isRedirection());
        self::assertSame('/settings/access', $response->headers->get('Location'));
        self::assertSame(['flash.project.api_key_created'], $session->getFlashBag()->peek('success'));
        self::assertNotEmpty($session->get('_beacon_last_api_key_dsn'));
        self::assertCount(1, $project->getApiKeys());
    }

    public function testRevokeKeyDeactivatesBelongingKey(): void
    {
        $user = new User()->setEmail('owner@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 2);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $key = ProjectApiKey::generate($project, 'ingest', 'pk_test_'.bin2hex(random_bytes(8)), 'sk_test');
        new ReflectionProperty(ProjectApiKey::class, 'id')->setValue($key, 11);
        self::assertTrue($key->isActive());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::once())->method('flush');

        $form = $this->createStub(FormInterface::class);
        $form->method('submit');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $controller = new ProjectApiKeyController(
            $em,
            new ProjectApiKeyFactory($em),
            new HumanFriendlyTokenGenerator(),
            new UserActionRecorder($em, new RequestStack()),
        );
        $session = $this->boot($controller, $user, $form, flash: true);

        $response = $controller->revokeKey($project, $key, Request::create('/revoke', Request::METHOD_POST));
        self::assertSame('/settings/access', $response->headers->get('Location'));
        self::assertFalse($key->isActive());
        self::assertSame(['flash.project.api_key_revoked'], $session->getFlashBag()->peek('success'));
    }

    /** @param FormInterface<mixed> $form */
    private function boot(object $controller, User $user, FormInterface $form, bool $flash = false): Session
    {
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/settings/access');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('https://beacon.test/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('security.token_storage', $tokenStorage);
        if ($flash) {
            $container->set('request_stack', $stack);
        }
        $controller->setContainer($container);

        return $session;
    }
}
