<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Repository\IssueSearchRepository;
use App\Issues\Service\DashboardNewInReleaseFilterResolver;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DashboardNewInReleaseFilterResolverTest extends TestCase
{
    /** @var list<Project> */
    private array $projects = [];

    private IssueSearchRepository&Stub $issueRepository;
    private DashboardNewInReleaseFilterResolver $resolver;

    protected function setUp(): void
    {
        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('findAccessibleByUser')->willReturnCallback(
            fn (): array => $this->projects,
        );
        $this->issueRepository = $this->createStub(IssueSearchRepository::class);
        $this->resolver = new DashboardNewInReleaseFilterResolver(
            new AccessibleProjectsProvider($projectRepository, new RequestStack()),
            $this->issueRepository,
        );
    }

    public function testDefaultsWithoutRelease(): void
    {
        $user = new User();
        $project = $this->project('Alpha');
        $this->projects = [$project];
        $this->issueRepository->method('findDistinctFirstReleasesAcrossProjects')->willReturn(['1.0.0']);

        $filters = $this->resolver->resolve($user, Request::create('/'));

        self::assertSame([$project], $filters->selectedProjects);
        self::assertNull($filters->project);
        self::assertNull($filters->release);
        self::assertSame(['1.0.0'], $filters->availableReleases);
    }

    public function testNormalizesReleaseAndScopesProjects(): void
    {
        $user = new User();
        $a = $this->project('A');
        $b = $this->project('B');
        $this->projects = [$a, $b];
        $this->issueRepository->method('findDistinctFirstReleasesAcrossProjects')->willReturn(['2.0.0']);

        $filters = $this->resolver->resolve($user, Request::create('/', Request::METHOD_GET, [
            'project' => $b->getUuid(),
            'release' => '  2.0.0  ',
        ]));

        self::assertSame($b, $filters->project);
        self::assertSame([$b], $filters->selectedProjects);
        self::assertSame('2.0.0', $filters->release);
        self::assertSame(['2.0.0'], $filters->availableReleases);
    }

    public function testBlankReleaseBecomesNull(): void
    {
        $user = new User();
        $this->projects = [];
        $this->issueRepository->method('findDistinctFirstReleasesAcrossProjects')->willReturn([]);

        $filters = $this->resolver->resolve($user, Request::create('/?release=%20%20'));

        self::assertNull($filters->release);
    }

    private function project(string $name): Project
    {
        $project = new Project();
        $project->setSlug(strtolower($name));
        $project->setName($name);

        return $project;
    }
}
