<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Command;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\PortableUserProvisioner;
use App\Project\Command\PreloadProjectsCommand;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectConfigPortability;
use App\Project\Service\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PreloadProjectsCommandTest extends TestCase
{
    public function testFailsWithoutBundleOption(): void
    {
        $tester = new CommandTester($this->command());
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('Missing --bundle', $tester->getDisplay());
    }

    public function testFailsOnWrongBundleSchema(): void
    {
        $path = $this->tempJson(['schema' => 'wrong', 'version' => 1, 'projects' => []]);
        try {
            $tester = new CommandTester($this->command());
            self::assertSame(1, $tester->execute(['--bundle' => $path]));
            self::assertStringContainsString('Expected schema', $tester->getDisplay());
        } finally {
            unlink($path);
        }
    }

    public function testFailsWhenNoAdminActor(): void
    {
        $path = $this->tempJson([
            'schema' => ProjectConfigPortability::SCHEMA,
            'version' => 1,
            'projects' => [['code' => 'acme', 'name' => 'Acme']],
        ]);
        $users = $this->createStub(UserRepository::class);
        $users->method('findFirstInstanceAdmin')->willReturn(null);
        try {
            $tester = new CommandTester($this->command(userRepository: $users));
            self::assertSame(1, $tester->execute(['--bundle' => $path]));
            self::assertStringContainsString('ROLE_ADMIN', $tester->getDisplay());
        } finally {
            unlink($path);
        }
    }

    public function testDryRunValidatesBundleAndApiKeys(): void
    {
        $uuid = '0192f3c4-5d6e-7a8b-9c0d-1e2f3a4b5c6d';
        $bundle = $this->tempJson([
            'schema' => ProjectConfigPortability::SCHEMA,
            'version' => 1,
            'projects' => [['code' => 'acme', 'name' => 'Acme', 'uuid' => $uuid]],
        ]);
        $keys = $this->tempJson([
            'schema' => PreloadProjectsCommand::API_KEYS_SCHEMA,
            'keys' => [[
                'project_code' => 'acme',
                'label' => 'preload',
                'public_key' => 'pub123',
                'secret_key' => 'sec123',
            ]],
        ]);
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $users = $this->createStub(UserRepository::class);
        $users->method('findFirstInstanceAdmin')->willReturn($admin);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('wrapInTransaction');

        try {
            $tester = new CommandTester($this->command(
                userRepository: $users,
                entityManager: $em,
            ));
            self::assertSame(0, $tester->execute([
                '--bundle' => $bundle,
                '--api-keys' => $keys,
                '--dry-run' => true,
            ]));
            self::assertStringContainsString('Dry-run OK', $tester->getDisplay());
            self::assertStringContainsString('api-keys valid', $tester->getDisplay());
        } finally {
            unlink($bundle);
            unlink($keys);
        }
    }

    public function testDryRunRejectsInvalidApiKeysShape(): void
    {
        $bundle = $this->tempJson([
            'schema' => ProjectConfigPortability::SCHEMA,
            'version' => 1,
            'projects' => [['code' => 'acme', 'name' => 'Acme']],
        ]);
        $keys = $this->tempJson([
            'schema' => PreloadProjectsCommand::API_KEYS_SCHEMA,
            'keys' => [['project_code' => 'acme']],
        ]);
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $users = $this->createStub(UserRepository::class);
        $users->method('findFirstInstanceAdmin')->willReturn($admin);

        try {
            $tester = new CommandTester($this->command(userRepository: $users));
            self::assertSame(1, $tester->execute([
                '--bundle' => $bundle,
                '--api-keys' => $keys,
                '--dry-run' => true,
            ]));
            self::assertStringContainsString('requires project_code, public_key, secret_key', $tester->getDisplay());
        } finally {
            unlink($bundle);
            unlink($keys);
        }
    }

    public function testDryRunRejectsInvalidProjectUuid(): void
    {
        $bundle = $this->tempJson([
            'schema' => ProjectConfigPortability::SCHEMA,
            'version' => 1,
            'projects' => [['code' => 'acme', 'name' => 'Acme', 'uuid' => 'not-a-uuid']],
        ]);
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $users = $this->createStub(UserRepository::class);
        $users->method('findFirstInstanceAdmin')->willReturn($admin);

        try {
            $tester = new CommandTester($this->command(userRepository: $users));
            self::assertSame(1, $tester->execute([
                '--bundle' => $bundle,
                '--dry-run' => true,
            ]));
            self::assertStringContainsString('invalid_uuid:not-a-uuid', $tester->getDisplay());
        } finally {
            unlink($bundle);
        }
    }

    private function command(
        ?UserRepository $userRepository = null,
        ?ProjectRepository $projectRepository = null,
        ?EntityManagerInterface $entityManager = null,
    ): PreloadProjectsCommand {
        $projectRepo = $projectRepository ?? $this->createStub(ProjectRepository::class);
        $em = $entityManager ?? $this->createStub(EntityManagerInterface::class);
        $portability = new ProjectConfigPortability(
            $projectRepo,
            $userRepository ?? $this->createStub(UserRepository::class),
            new PortableUserProvisioner(
                $this->createStub(UserRepository::class),
                $this->createStub(UserPasswordHasherInterface::class),
            ),
            new ProjectFactory($projectRepo, new ProjectApiKeyFactory($em)),
        );

        return new PreloadProjectsCommand(
            $portability,
            $userRepository ?? $this->createStub(UserRepository::class),
            $projectRepo,
            new ProjectApiKeyFactory($em),
            $em,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function tempJson(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'beacon-preload-');
        self::assertNotFalse($path);
        file_put_contents($path, json_encode($data, \JSON_THROW_ON_ERROR));

        return $path;
    }
}
