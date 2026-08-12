<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Service\ProjectApiKeyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class ProjectApiKeyFactoryTest extends TestCase
{
    public function testCreateUsesProvidedPublicKey(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');

        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');

        $key = new ProjectApiKeyFactory($em)->create($project, 'CI', 'fixed-public', 'fixed-secret');

        self::assertSame('fixed-public', $key->getPublicKey());
        self::assertSame('CI', $key->getLabel());
        self::assertSame($project, $key->getProject());
    }

    public function testCreateRegeneratesWhenCollisionThenSucceeds(): void
    {
        /** @var EntityRepository<ProjectApiKey>&MockObject $repo */
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects(self::exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(new ProjectApiKey(), null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::any())->method('getRepository')->with(ProjectApiKey::class)->willReturn($repo);

        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');

        $key = new ProjectApiKeyFactory($em)->create($project, 'Auto');
        self::assertSame(32, \strlen($key->getPublicKey()));
        self::assertSame('Auto', $key->getLabel());
    }

    public function testCreateFallsBackAfterEightCollisions(): void
    {
        /** @var EntityRepository<ProjectApiKey>&Stub $repo */
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(new ProjectApiKey());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::any())->method('getRepository')->with(ProjectApiKey::class)->willReturn($repo);

        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');

        $key = new ProjectApiKeyFactory($em)->create($project, 'Fallback');
        self::assertSame(48, \strlen($key->getPublicKey()));
    }
}
