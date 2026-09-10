<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Command;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\PortableUserProvisioner;
use App\Project\Command\PreloadProjectsCommand;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectConfigPortability;
use App\Project\Service\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use InvalidArgumentException;
use LogicException;
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

    public function testRejectsUnreadableEmptyInvalidAndNonObjectJson(): void
    {
        $missing = sys_get_temp_dir().'/beacon-preload-missing-'.bin2hex(random_bytes(4)).'.json';
        $empty = $this->tempRaw('');
        $invalid = $this->tempRaw('{invalid');
        $scalar = $this->tempRaw('"value"');

        try {
            foreach ([
                [$missing, 'Cannot read'],
                [$empty, 'Empty file'],
                [$invalid, 'Invalid JSON'],
                [$scalar, 'JSON root must be an object'],
            ] as [$path, $message]) {
                $tester = new CommandTester($this->command());
                self::assertSame(1, $tester->execute(['--bundle' => $path]));
                self::assertStringContainsString($message, $tester->getDisplay());
            }
        } finally {
            unlink($empty);
            unlink($invalid);
            unlink($scalar);
        }
    }

    public function testActorEmailMustResolveToAnAdmin(): void
    {
        $path = $this->validBundle();
        $user = new User();
        $user->setEmail('member@example.com');
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn($user);

        try {
            $tester = new CommandTester($this->command(userRepository: $users));
            self::assertSame(1, $tester->execute([
                '--bundle' => $path,
                '--actor-email' => 'member@example.com',
                '--dry-run' => true,
            ]));
            self::assertStringContainsString('No ROLE_ADMIN actor found', $tester->getDisplay());
        } finally {
            unlink($path);
        }
    }

    public function testDryRunRejectsApiKeysSchemaRowsAndRowTypes(): void
    {
        $bundle = $this->validBundle();
        $admin = $this->admin();
        $users = $this->createStub(UserRepository::class);
        $users->method('findFirstInstanceAdmin')->willReturn($admin);
        $cases = [
            [['schema' => 'wrong', 'keys' => []], 'Expected schema'],
            [['schema' => PreloadProjectsCommand::API_KEYS_SCHEMA, 'keys' => 'wrong'], 'must contain a "keys" array'],
            [['schema' => PreloadProjectsCommand::API_KEYS_SCHEMA, 'keys' => ['wrong']], 'keys[0] must be an object'],
        ];

        try {
            foreach ($cases as [$payload, $message]) {
                $keys = $this->tempJson($payload);
                try {
                    $tester = new CommandTester($this->command(userRepository: $users));
                    self::assertSame(1, $tester->execute([
                        '--bundle' => $bundle,
                        '--api-keys' => $keys,
                        '--dry-run' => true,
                    ]));
                    self::assertStringContainsString($message, $tester->getDisplay());
                } finally {
                    unlink($keys);
                }
            }
        } finally {
            unlink($bundle);
        }
    }

    public function testNonDryRunImportsProjectsAndCreatesAndSkipsApiKeys(): void
    {
        $bundle = $this->validBundle();
        $keys = $this->tempJson([
            'schema' => PreloadProjectsCommand::API_KEYS_SCHEMA,
            'keys' => [
                [
                    'project_code' => 'acme',
                    'label' => 'existing',
                    'public_key' => 'existing-public',
                    'secret_key' => 'existing-secret',
                ],
                [
                    'project_code' => ' ACME ',
                    'label' => '',
                    'public_key' => 'created-public',
                    'secret_key' => 'created-secret',
                ],
            ],
        ]);
        $admin = $this->admin();
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn($admin);
        $users->method('findIndexedByEmails')->willReturn([]);

        $storedProject = null;
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use (&$storedProject): ?Project {
                if (isset($criteria['code']) && $storedProject instanceof Project && $storedProject->getCode() === $criteria['code']) {
                    return $storedProject;
                }

                return null;
            },
        );
        $projects->expects(self::atLeastOnce())->method('save')->willReturnCallback(
            static function (Project $project) use (&$storedProject): void {
                $storedProject = $project;
            },
        );
        $projects->expects(self::once())->method('hydrateMembershipsForProjects');

        $existingProject = (new Project())->setName('Existing')->setSlug('existing');
        $existingKey = ProjectApiKey::generate($existingProject, 'Existing', 'existing-public', 'existing-secret');
        $keyRepository = $this->createStub(EntityRepository::class);
        $keyRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?ProjectApiKey => 'existing-public' === ($criteria['publicKey'] ?? null) ? $existingKey : null,
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeastOnce())->method('getRepository')->with(ProjectApiKey::class)->willReturn($keyRepository);
        $entityManager->expects(self::once())->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $callback): mixed => $callback(),
        );

        try {
            $tester = new CommandTester($this->command(
                userRepository: $users,
                projectRepository: $projects,
                entityManager: $entityManager,
            ));
            self::assertSame(0, $tester->execute([
                '--bundle' => $bundle,
                '--api-keys' => $keys,
                '--actor-email' => 'admin@example.com',
            ]));
            self::assertStringContainsString('Projects upserted=1', $tester->getDisplay());
            self::assertStringContainsString('skip public_key already present (existing-public)', $tester->getDisplay());
            self::assertStringContainsString('created key label=preload project=acme public=created-public', $tester->getDisplay());
            self::assertStringContainsString('API keys created=1 skipped=1', $tester->getDisplay());
            self::assertInstanceOf(Project::class, $storedProject);
            self::assertCount(2, $storedProject->getApiKeys());
        } finally {
            unlink($bundle);
            unlink($keys);
        }
    }

    public function testNonDryRunReportsSkippedMembershipsAndWarnings(): void
    {
        $bundle = $this->validBundle();
        $admin = $this->admin();
        $users = $this->createStub(UserRepository::class);
        $users->method('findFirstInstanceAdmin')->willReturn($admin);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('wrapInTransaction')->willReturn([
            'projects' => [
                'projects_upserted' => 1,
                'users_created' => 0,
                'memberships_applied' => 0,
                'memberships_skipped' => ['missing@example.com'],
                'warnings' => ['Owner membership was preserved.'],
            ],
            'keys' => null,
        ]);

        try {
            $tester = new CommandTester($this->command(userRepository: $users, entityManager: $entityManager));
            self::assertSame(0, $tester->execute(['--bundle' => $bundle]));
            self::assertStringContainsString('Memberships skipped: missing@example.com', $tester->getDisplay());
            self::assertStringContainsString('Owner membership was preserved.', $tester->getDisplay());
        } finally {
            unlink($bundle);
        }
    }

    public function testNonDryRunRejectsUnknownApiKeyProject(): void
    {
        $bundle = $this->validBundle();
        $keys = $this->tempJson([
            'schema' => PreloadProjectsCommand::API_KEYS_SCHEMA,
            'keys' => [[
                'project_code' => 'missing',
                'public_key' => 'missing-public',
                'secret_key' => 'missing-secret',
            ]],
        ]);
        $admin = $this->admin();
        $users = $this->createStub(UserRepository::class);
        $users->method('findFirstInstanceAdmin')->willReturn($admin);
        $users->method('findIndexedByEmails')->willReturn([]);
        $projects = $this->createStub(ProjectRepository::class);
        $keyRepository = $this->createStub(EntityRepository::class);
        $keyRepository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($keyRepository);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn (callable $callback): mixed => $callback());

        try {
            $tester = new CommandTester($this->command(
                userRepository: $users,
                projectRepository: $projects,
                entityManager: $entityManager,
            ));
            self::assertSame(1, $tester->execute(['--bundle' => $bundle, '--api-keys' => $keys]));
            self::assertStringContainsString('Unknown project_code "missing"', $tester->getDisplay());
        } finally {
            unlink($bundle);
            unlink($keys);
        }
    }

    public function testNonDryRunReportsExpectedAndUnexpectedTransactionFailures(): void
    {
        $bundle = $this->validBundle();
        $admin = $this->admin();
        $users = $this->createStub(UserRepository::class);
        $users->method('findFirstInstanceAdmin')->willReturn($admin);

        try {
            foreach ([
                [new InvalidArgumentException('invalid import'), 'invalid import'],
                [new LogicException('database unavailable'), 'Preload failed: database unavailable'],
            ] as [$exception, $message]) {
                $entityManager = $this->createStub(EntityManagerInterface::class);
                $entityManager->method('wrapInTransaction')->willThrowException($exception);
                $tester = new CommandTester($this->command(userRepository: $users, entityManager: $entityManager));
                self::assertSame(1, $tester->execute(['--bundle' => $bundle]));
                self::assertStringContainsString($message, $tester->getDisplay());
            }
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

    private function tempRaw(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'beacon-preload-');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);

        return $path;
    }

    private function validBundle(): string
    {
        return $this->tempJson([
            'schema' => ProjectConfigPortability::SCHEMA,
            'version' => 1,
            'projects' => [['code' => 'acme', 'name' => 'Acme']],
        ]);
    }

    private function admin(): User
    {
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);

        return $admin;
    }
}
