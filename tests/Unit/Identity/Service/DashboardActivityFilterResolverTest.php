<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Service\DashboardActivityFilterResolver;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DashboardActivityFilterResolverTest extends TestCase
{
    /** @var list<Project> */
    private array $projects = [];

    private DashboardActivityFilterResolver $resolver;

    protected function setUp(): void
    {
        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('findAccessibleByUser')->willReturnCallback(
            fn (): array => $this->projects,
        );
        $this->resolver = new DashboardActivityFilterResolver(
            new AccessibleProjectsProvider($projectRepository, new RequestStack()),
        );
    }

    public function testAllAccessibleUuidsWhenNoProjectSelected(): void
    {
        $user = new User();
        $a = $this->project('A');
        $b = $this->project('B');
        $this->projects = [$a, $b];

        $filters = $this->resolver->resolve($user, Request::create('/dashboard/activity'));

        self::assertNull($filters->project);
        self::assertSame([$a->getUuid(), $b->getUuid()], $filters->projectUuids);
    }

    public function testSingleUuidWhenProjectSelected(): void
    {
        $user = new User();
        $a = $this->project('A');
        $b = $this->project('B');
        $this->projects = [$a, $b];

        $filters = $this->resolver->resolve($user, Request::create('/?project='.$b->getUuid()));

        self::assertSame($b, $filters->project);
        self::assertSame([$b->getUuid()], $filters->projectUuids);
    }

    private function project(string $name): Project
    {
        $project = new Project();
        $project->setSlug(strtolower($name));
        $project->setName($name);

        return $project;
    }
}
