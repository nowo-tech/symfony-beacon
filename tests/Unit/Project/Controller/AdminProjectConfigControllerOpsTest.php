<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\PortableUserProvisioner;
use App\Identity\Service\UserActionRecorder;
use App\Project\Controller\AdminProjectConfigController;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectConfigPortability;
use App\Project\Service\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AdminProjectConfigControllerOpsTest extends TestCase
{
    public function testExportAllFiltersByIdsQuery(): void
    {
        $user = new User()->setEmail('admin@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $selected = new Project()->setName('Acme')->setSlug('acme')->setCode('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($selected, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findByUuids')->willReturn([$selected]);
        $projects->method('hydrateMembershipsForProjects');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        $controller = $this->controller($projects, $em);
        $this->boot($controller, $user);

        $response = $controller->exportAll(Request::create('/export', Request::METHOD_GET, [
            'ids' => ['aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa', '', 12],
        ]));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $payload['projects']);
        self::assertSame('acme', $payload['projects'][0]['code']);
    }

    public function testExportOneUsesSafeCodeInFilename(): void
    {
        $user = new User()->setEmail('admin@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $project = new Project()->setName('Acme')->setSlug('acme')->setCode('Acme Prod!');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('hydrateMembershipsForProjects');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        $controller = $this->controller($projects, $em);
        $this->boot($controller, $user);

        $response = $controller->exportOne($project);
        self::assertStringContainsString(
            'beacon-project-acme-prod-.json',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function testImportInvalidCsrfFlashesError(): void
    {
        $user = new User()->setEmail('admin@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $projects = $this->createStub(ProjectRepository::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $controller = $this->controller($projects, $em);
        $session = $this->boot($controller, $user, $form, flash: true);

        $response = $controller->import(Request::create('/import', Request::METHOD_POST));
        self::assertTrue($response->isRedirection());
        self::assertSame('/admin/projects', $response->headers->get('Location'));
        self::assertSame(['flash.project.config_invalid_csrf'], $session->getFlashBag()->peek('error'));
    }

    private function controller(ProjectRepository $projects, EntityManagerInterface $em): AdminProjectConfigController
    {
        return new AdminProjectConfigController(
            $projects,
            new ProjectConfigPortability(
                $projects,
                $this->createStub(UserRepository::class),
                new PortableUserProvisioner(
                    $this->createStub(UserRepository::class),
                    $this->createStub(UserPasswordHasherInterface::class),
                ),
                new ProjectFactory($projects, new ProjectApiKeyFactory($em)),
            ),
            new UserActionRecorder($em, new RequestStack()),
        );
    }

    /** @param FormInterface<mixed>|null $form */
    private function boot(
        object $controller,
        User $user,
        ?FormInterface $form = null,
        bool $flash = false,
    ): Session {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_ADMIN', 'ROLE_USER']));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/projects');

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $router);
        if ($form instanceof FormInterface) {
            $formFactory = $this->createStub(FormFactoryInterface::class);
            $formFactory->method('create')->willReturn($form);
            $container->set('form.factory', $formFactory);
        }
        if ($flash) {
            $container->set('request_stack', $stack);
        }
        $controller->setContainer($container);

        return $session;
    }
}
