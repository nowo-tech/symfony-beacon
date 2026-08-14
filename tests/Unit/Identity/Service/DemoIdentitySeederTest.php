<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Command\SeedDemoCommand;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\DemoIdentitySeeder;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DemoIdentitySeederTest extends TestCase
{
    public function testSeedCreatesDemoUserAndDogfoodProject(): void
    {
        $savedUsers = [];
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn(null);
        $users->method('save')->willReturnCallback(static function (User $user) use (&$savedUsers): void {
            new ReflectionProperty(User::class, 'id')->setValue($user, 1);
            $savedUsers[] = $user;
        });
        $users->method('findAll')->willReturnCallback(static fn (): array => $savedUsers);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn(null);
        $projects->method('save');

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $em = $this->createStub(EntityManagerInterface::class);
        $apiKeys = new ProjectApiKeyFactory($em);
        $factory = new ProjectFactory($projects, $apiKeys);
        $seeder = new DemoIdentitySeeder($users, $projects, $hasher, $factory, $apiKeys);

        $result = $seeder->seed();
        self::assertTrue($result['user_created']);
        self::assertTrue($result['project_created']);
        self::assertSame(SeedDemoCommand::DEMO_PROJECT_SLUG, $result['project']->getSlug());
        self::assertInstanceOf(ProjectApiKey::class, $result['api_key']);
        self::assertSame(SeedDemoCommand::DEMO_PUBLIC_KEY, $result['api_key']->getPublicKey());
    }

    public function testEnsureDemoProjectThrowsWithoutOwner(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneBy')->willReturn(null);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn(null);
        $em = $this->createStub(EntityManagerInterface::class);
        $apiKeys = new ProjectApiKeyFactory($em);
        $seeder = new DemoIdentitySeeder(
            $users,
            $projects,
            $this->createStub(UserPasswordHasherInterface::class),
            new ProjectFactory($projects, $apiKeys),
            $apiKeys,
        );

        $this->expectException(LogicException::class);
        $seeder->ensureDemoProject();
    }

    public function testEnsureDemoProjectReturnsExistingAfterSync(): void
    {
        $project = new Project()
            ->setName('old')
            ->setSlug(SeedDemoCommand::LEGACY_DEMO_PROJECT_SLUG)
            ->setDescription('old');
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Project => ($criteria['slug'] ?? null) === SeedDemoCommand::LEGACY_DEMO_PROJECT_SLUG ? $project : null);
        $saved = 0;
        $projects->method('save')->willReturnCallback(static function () use (&$saved): void {
            ++$saved;
        });

        $em = $this->createStub(EntityManagerInterface::class);
        $apiKeys = new ProjectApiKeyFactory($em);
        $seeder = new DemoIdentitySeeder(
            $this->createStub(UserRepository::class),
            $projects,
            $this->createStub(UserPasswordHasherInterface::class),
            new ProjectFactory($projects, $apiKeys),
            $apiKeys,
        );

        $result = $seeder->ensureDemoProject();
        self::assertFalse($result['project_created']);
        self::assertSame(SeedDemoCommand::DEMO_PROJECT_SLUG, $result['project']->getSlug());
        self::assertSame(SeedDemoCommand::DEMO_PROJECT_NAME, $result['project']->getName());
        self::assertGreaterThanOrEqual(1, $saved);
    }
}
