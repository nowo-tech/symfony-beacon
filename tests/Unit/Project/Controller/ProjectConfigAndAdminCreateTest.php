<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\PortableUserProvisioner;
use App\Identity\Service\UserActionRecorder;
use App\Project\Controller\AdminProjectController;
use App\Project\Controller\ProjectConfigController;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectConfigPortability;
use App\Project\Service\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ProjectConfigAndAdminCreateTest extends TestCase
{
    public function testProjectConfigExportDownloadsJsonAndRecordsAction(): void
    {
        $user = new User()->setEmail('owner@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 3);
        $project = new Project()->setName('Acme')->setSlug('acme')->setCode('acme-prod');

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('hydrateMembershipsForProjects');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $controller = new ProjectConfigController(
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

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        $response = $controller->export($project);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('beacon-project-acme-prod.json', (string) $response->headers->get('Content-Disposition'));
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(ProjectConfigPortability::SCHEMA, $payload['schema']);
        self::assertSame('acme-prod', $payload['projects'][0]['code']);
    }

    public function testAdminCreateProjectPersistsAndRecords(): void
    {
        $owner = new User()->setEmail('owner@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($owner, 9);

        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn(null);
        $projects->expects(self::once())->method('save');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::once())->method('flush');

        $controller = new ReflectionClass(AdminProjectController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminProjectController::class, 'projectFactory')->setValue(
            $controller,
            new ProjectFactory($projects, new ProjectApiKeyFactory($em)),
        );
        new ReflectionProperty(AdminProjectController::class, 'projectRepository')->setValue($controller, $projects);
        new ReflectionProperty(AdminProjectController::class, 'actionRecorder')->setValue(
            $controller,
            new UserActionRecorder($em, new RequestStack()),
        );
        new ReflectionProperty(AdminProjectController::class, 'entityManager')->setValue($controller, $em);

        $method = new ReflectionMethod(AdminProjectController::class, 'createProject');
        $project = $method->invoke($controller, 'New Project', '  hello  ', $owner);

        self::assertSame('New Project', $project->getName());
        self::assertSame('hello', $project->getDescription());
        self::assertNotSame('', $project->getSlug());
    }

    public function testAdminCreateProjectNullsBlankDescription(): void
    {
        $owner = new User()->setEmail('owner@example.com');
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn(null);
        $projects->method('save');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        $controller = new ReflectionClass(AdminProjectController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminProjectController::class, 'projectFactory')->setValue(
            $controller,
            new ProjectFactory($projects, new ProjectApiKeyFactory($em)),
        );
        new ReflectionProperty(AdminProjectController::class, 'projectRepository')->setValue($controller, $projects);
        new ReflectionProperty(AdminProjectController::class, 'actionRecorder')->setValue(
            $controller,
            new UserActionRecorder($em, new RequestStack()),
        );
        new ReflectionProperty(AdminProjectController::class, 'entityManager')->setValue($controller, $em);

        $method = new ReflectionMethod(AdminProjectController::class, 'createProject');
        $project = $method->invoke($controller, 'Blank Desc', '   ', $owner);
        self::assertNull($project->getDescription());
    }
}
