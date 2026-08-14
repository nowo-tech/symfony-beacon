<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\PortableUserProvisioner;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectConfigPortability;
use App\Project\Service\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectConfigPortabilityTest extends TestCase
{
    public function testExportIncludesCodeAndMembershipsWithoutSecrets(): void
    {
        $owner = $this->user('owner@example.com', 'Owner');
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');
        $project->setCode('acme-prod');
        $membership = new ProjectMembership();
        $membership->setUser($owner);
        $membership->setRole(ProjectRole::Owner);
        $project->addMembership($membership);

        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->expects(self::once())->method('hydrateMembershipsForProjects')->with([$project]);

        $service = new ProjectConfigPortability(
            $projectRepo,
            $this->createStub(UserRepository::class),
            new PortableUserProvisioner(
                $this->createStub(UserRepository::class),
                $this->createStub(UserPasswordHasherInterface::class),
            ),
            new ProjectFactory($projectRepo, new ProjectApiKeyFactory($this->createStub(EntityManagerInterface::class))),
        );

        $payload = $service->export([$project]);
        self::assertSame(ProjectConfigPortability::SCHEMA, $payload['schema']);
        self::assertSame(1, $payload['version']);
        self::assertCount(1, $payload['projects']);
        self::assertSame('acme-prod', $payload['projects'][0]['code']);
        self::assertSame('owner@example.com', $payload['projects'][0]['memberships'][0]['email']);
        self::assertArrayNotHasKey('api_keys', $payload['projects'][0]);
    }

    public function testPanelImportSkipsUnknownEmails(): void
    {
        $actor = $this->user('admin@example.com', 'Admin');
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');
        $project->setCode('acme-prod');
        $ownerMembership = new ProjectMembership();
        $ownerMembership->setUser($actor);
        $ownerMembership->setRole(ProjectRole::Owner);
        $project->addMembership($ownerMembership);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects(self::once())->method('findIndexedByEmails')->willReturnCallback(
            static function (array $emails) use ($actor): array {
                $map = [];
                foreach ($emails as $email) {
                    if ('admin@example.com' === $email) {
                        $map[$email] = $actor;
                    }
                }

                return $map;
            },
        );

        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->expects(self::atLeastOnce())->method('hydrateMembershipsForProjects');
        $projectRepo->expects(self::atLeastOnce())->method('save');

        $service = new ProjectConfigPortability(
            $projectRepo,
            $userRepo,
            new PortableUserProvisioner(
                $this->createStub(UserRepository::class),
                $this->createStub(UserPasswordHasherInterface::class),
            ),
            new ProjectFactory($projectRepo, new ProjectApiKeyFactory($this->createStub(EntityManagerInterface::class))),
        );

        $payload = [
            'schema' => ProjectConfigPortability::SCHEMA,
            'version' => 1,
            'projects' => [[
                'code' => 'acme-prod',
                'uuid' => $project->getUuid(),
                'slug' => 'acme',
                'name' => 'Acme Updated',
                'description' => null,
                'ingest_enabled' => true,
                'memberships' => [
                    ['email' => 'admin@example.com', 'display_name' => 'Admin', 'role' => 'owner', 'active' => true],
                    ['email' => 'missing@example.com', 'display_name' => 'Missing', 'role' => 'member', 'active' => true],
                ],
            ]],
        ];

        $result = $service->importPanel($payload, $project, $actor);
        self::assertSame(1, $result['projects_upserted']);
        self::assertSame(0, $result['users_created']);
        self::assertSame(['missing@example.com'], $result['memberships_skipped']);
        self::assertSame('Acme Updated', $project->getName());
    }

    public function testPanelImportDoesNotPromoteFullToOwner(): void
    {
        $actor = $this->user('full@example.com', 'Full');
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');
        $project->setCode('acme-prod');
        $membership = new ProjectMembership();
        $membership->setUser($actor);
        $membership->setRole(ProjectRole::Full);
        $project->addMembership($membership);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findIndexedByEmails')->willReturn(['full@example.com' => $actor]);

        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->method('hydrateMembershipsForProjects');
        $projectRepo->expects(self::atLeastOnce())->method('save');

        $service = new ProjectConfigPortability(
            $projectRepo,
            $userRepo,
            new PortableUserProvisioner(
                $this->createStub(UserRepository::class),
                $this->createStub(UserPasswordHasherInterface::class),
            ),
            new ProjectFactory($projectRepo, new ProjectApiKeyFactory($this->createStub(EntityManagerInterface::class))),
        );

        $result = $service->importPanel([
            'schema' => ProjectConfigPortability::SCHEMA,
            'version' => 1,
            'projects' => [[
                'code' => 'acme-prod',
                'uuid' => $project->getUuid(),
                'slug' => 'acme',
                'name' => 'Acme',
                'memberships' => [
                    ['email' => 'full@example.com', 'display_name' => 'Full', 'role' => 'owner', 'active' => true],
                ],
            ]],
        ], $project, $actor);

        self::assertSame(ProjectRole::Full, $membership->getRole());
        self::assertNotEmpty($result['warnings']);
        self::assertStringContainsString('owner ignored', $result['warnings'][0]);
    }

    public function testPanelImportPreservesLastActiveOwner(): void
    {
        $actor = $this->user('owner@example.com', 'Owner');
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');
        $project->setCode('acme-prod');
        $membership = new ProjectMembership();
        $membership->setUser($actor);
        $membership->setRole(ProjectRole::Owner);
        $membership->setActive(true);
        $project->addMembership($membership);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findIndexedByEmails')->willReturn(['owner@example.com' => $actor]);

        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->method('hydrateMembershipsForProjects');
        $projectRepo->expects(self::atLeastOnce())->method('save');

        $service = new ProjectConfigPortability(
            $projectRepo,
            $userRepo,
            new PortableUserProvisioner(
                $this->createStub(UserRepository::class),
                $this->createStub(UserPasswordHasherInterface::class),
            ),
            new ProjectFactory($projectRepo, new ProjectApiKeyFactory($this->createStub(EntityManagerInterface::class))),
        );

        $result = $service->importPanel([
            'schema' => ProjectConfigPortability::SCHEMA,
            'version' => 1,
            'projects' => [[
                'code' => 'acme-prod',
                'uuid' => $project->getUuid(),
                'slug' => 'acme',
                'name' => 'Acme',
                'memberships' => [
                    ['email' => 'owner@example.com', 'display_name' => 'Owner', 'role' => 'member', 'active' => false],
                ],
            ]],
        ], $project, $actor);

        self::assertSame(ProjectRole::Owner, $membership->getRole());
        self::assertTrue($membership->isActive());
        self::assertNotEmpty($result['warnings']);
        self::assertStringContainsString('last active owner', $result['warnings'][0]);
    }

    public function testPanelImportRejectsCodeMismatch(): void
    {
        $actor = $this->user('admin@example.com', 'Admin');
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');
        $project->setCode('acme-prod');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('code_mismatch');

        $this->service()->importPanel([
            'schema' => ProjectConfigPortability::SCHEMA,
            'version' => 1,
            'projects' => [[
                'code' => 'other-code',
                'slug' => 'other',
                'name' => 'Other',
                'memberships' => [],
            ]],
        ], $project, $actor);
    }

    public function testImportAdminCreatesMissingProjectAndUsers(): void
    {
        $actor = $this->user('admin@example.com', 'Admin');
        new ReflectionProperty(User::class, 'id')->setValue($actor, 1);

        /** @var array<string, User> $createdUsers */
        $createdUsers = [];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findIndexedByEmails')->willReturnCallback(
            static function (array $emails) use ($actor, &$createdUsers): array {
                $map = ['admin@example.com' => $actor];
                foreach ($emails as $email) {
                    if (isset($createdUsers[$email])) {
                        $map[$email] = $createdUsers[$email];
                    }
                }

                return $map;
            },
        );
        $userRepo->method('save')->willReturnCallback(static function (User $user) use (&$createdUsers): void {
            $createdUsers[strtolower($user->getEmail())] = $user;
            if (null === $user->getId()) {
                new ReflectionProperty(User::class, 'id')->setValue($user, 10 + \count($createdUsers));
            }
        });

        $saved = [];
        $projectRepo = $this->createStub(ProjectRepository::class);
        $projectRepo->method('findOneBy')->willReturn(null);
        $projectRepo->method('hydrateMembershipsForProjects');
        $projectRepo->method('save')->willReturnCallback(static function (Project $project) use (&$saved): void {
            if (null === $project->getId()) {
                new ReflectionProperty(Project::class, 'id')->setValue($project, 42);
            }
            $saved[] = $project;
        });

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hash');
        $service = new ProjectConfigPortability(
            $projectRepo,
            $userRepo,
            new PortableUserProvisioner($userRepo, $hasher),
            new ProjectFactory($projectRepo, new ProjectApiKeyFactory($this->createStub(EntityManagerInterface::class))),
        );

        $result = $service->importAdmin([
            'schema' => ProjectConfigPortability::SCHEMA,
            'version' => 1,
            'projects' => [[
                'code' => 'new-proj',
                'slug' => 'new-proj',
                'name' => 'New Project',
                'description' => 'Demo',
                'ingest_enabled' => true,
                'retention_days' => 14,
                'memberships' => [
                    ['email' => 'admin@example.com', 'display_name' => 'Admin', 'role' => 'owner', 'active' => true],
                    ['email' => 'newbie@example.com', 'display_name' => 'Newbie', 'role' => 'member', 'active' => true],
                ],
            ]],
        ], $actor);

        self::assertSame(1, $result['projects_upserted']);
        self::assertSame(1, $result['users_created']);
        self::assertGreaterThanOrEqual(2, $result['memberships_applied']);
        self::assertNotEmpty($saved);
        self::assertSame('new-proj', $saved[0]->getCode());
        self::assertSame(14, $saved[0]->getRetentionDays());
    }

    private function service(): ProjectConfigPortability
    {
        $projectRepo = $this->createStub(ProjectRepository::class);

        return new ProjectConfigPortability(
            $projectRepo,
            $this->createStub(UserRepository::class),
            new PortableUserProvisioner(
                $this->createStub(UserRepository::class),
                $this->createStub(UserPasswordHasherInterface::class),
            ),
            new ProjectFactory($projectRepo, new ProjectApiKeyFactory($this->createStub(EntityManagerInterface::class))),
        );
    }

    private function user(string $email, string $name): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($name);
        $user->setPassword('hash');

        return $user;
    }
}
