<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class ProjectFactoryTest extends TestCase
{
    public function testCreateUsesDeterministicSlugAndApiKeyMaterial(): void
    {
        $owner = new User();
        $owner->setEmail('owner@example.com');
        $owner->setDisplayName('Owner');

        $repository = $this->createStub(ProjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $apiKeyRepo = $this->createStub(EntityRepository::class);
        $apiKeyRepo->method('findOneBy')->willReturn(null);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($apiKeyRepo);

        $factory = new ProjectFactory(
            $repository,
            new ProjectApiKeyFactory($em),
        );
        $project = $factory->create(
            $owner,
            'Symfony Beacon',
            'Dogfood',
            'symfony-beacon',
            'Symfony Beacon key',
            'fixed-public',
            'fixed-secret',
        );

        self::assertSame('Symfony Beacon', $project->getName());
        self::assertSame('symfony-beacon', $project->getSlug());
        self::assertSame('Dogfood', $project->getDescription());
        self::assertCount(1, $project->getMemberships());
        self::assertSame(ProjectRole::Owner, $project->getMemberships()->first()->getRole());
        self::assertSame($owner, $project->getMemberships()->first()->getUser());
        $key = $project->getApiKeys()->first();
        self::assertInstanceOf(ProjectApiKey::class, $key);
        self::assertSame('fixed-public', $key->getPublicKey());
        self::assertTrue($key->matchesSecret('fixed-secret'));
        self::assertSame(ProjectApiKey::hashSecret('fixed-secret'), $key->getSecretHash());
        self::assertSame('Symfony Beacon key', $key->getLabel());
    }

    public function testCreateDerivesSlugWhenNotProvided(): void
    {
        $owner = new User();
        $repository = $this->createStub(ProjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $apiKeyRepo = $this->createStub(EntityRepository::class);
        $apiKeyRepo->method('findOneBy')->willReturn(null);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($apiKeyRepo);

        $factory = new ProjectFactory(
            $repository,
            new ProjectApiKeyFactory($em),
        );
        $project = $factory->create($owner, 'My Cool App');

        self::assertSame('my-cool-app', $project->getSlug());
        self::assertNull($project->getDescription());
        self::assertInstanceOf(ProjectApiKey::class, $project->getApiKeys()->first());
        self::assertSame('Default', $project->getApiKeys()->first()->getLabel());
    }

    public function testCreateAppendsSuffixWhenSlugAlreadyTaken(): void
    {
        $owner = new User();
        $existing = new Project();
        $existing->setSlug('symfony-beacon');

        $repository = $this->createStub(ProjectRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $apiKeyRepo = $this->createStub(EntityRepository::class);
        $apiKeyRepo->method('findOneBy')->willReturn(null);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($apiKeyRepo);

        $factory = new ProjectFactory(
            $repository,
            new ProjectApiKeyFactory($em),
        );
        $project = $factory->create(
            $owner,
            'Symfony Beacon',
            null,
            'symfony-beacon',
        );

        self::assertNotSame('symfony-beacon', $project->getSlug());
        self::assertStringStartsWith('symfony-beacon-', $project->getSlug());
    }
}
