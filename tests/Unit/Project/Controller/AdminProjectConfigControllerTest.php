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
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AdminProjectConfigControllerTest extends TestCase
{
    public function testJsonDownloadAndExportAll(): void
    {
        $user = new User()->setEmail('admin@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $project = new Project()->setName('Acme')->setSlug('acme')->setCode('acme-prod');

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAllOrdered')->willReturn([$project]);
        $projects->method('hydrateMembershipsForProjects');

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->method('flush');

        $controller = new AdminProjectConfigController(
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

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_ADMIN', 'ROLE_USER']));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        $method = new ReflectionMethod(AdminProjectConfigController::class, 'jsonDownload');
        $download = $method->invoke($controller, ['ok' => true], 'beacon-test.json');
        self::assertSame(Response::HTTP_OK, $download->getStatusCode());
        self::assertStringContainsString('beacon-test.json', (string) $download->headers->get('Content-Disposition'));

        $export = $controller->exportAll(Request::create('/admin/projects/export'));
        self::assertSame(Response::HTTP_OK, $export->getStatusCode());
        $payload = json_decode((string) $export->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(ProjectConfigPortability::SCHEMA, $payload['schema']);
        self::assertSame('acme-prod', $payload['projects'][0]['code']);
        self::assertNotEmpty($persisted);
    }
}
