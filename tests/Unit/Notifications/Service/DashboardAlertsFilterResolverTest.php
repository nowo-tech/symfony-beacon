<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Identity\Entity\User;
use App\Notifications\Service\DashboardAlertsFilterResolver;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use App\Project\Service\DashboardProjectSelectionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DashboardAlertsFilterResolverTest extends TestCase
{
    /** @var list<Project> */
    private array $projects = [];

    private DashboardAlertsFilterResolver $resolver;

    protected function setUp(): void
    {
        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('findAccessibleByUser')->willReturnCallback(
            fn (): array => $this->projects,
        );
        $this->resolver = new DashboardAlertsFilterResolver(
            new DashboardProjectSelectionResolver(
                new AccessibleProjectsProvider($projectRepository, new RequestStack()),
            ),
        );
    }

    public function testDefaultsToAllAccessibleProjects(): void
    {
        $user = new User();
        $a = $this->project('A');
        $b = $this->project('B');
        $this->projects = [$a, $b];

        $filters = $this->resolver->resolve($user, Request::create('/dashboard/alerts'));

        self::assertNull($filters->project);
        self::assertSame([$a, $b], $filters->selectedProjects);
    }

    public function testScopesToSelectedProject(): void
    {
        $user = new User();
        $a = $this->project('A');
        $b = $this->project('B');
        $this->projects = [$a, $b];

        $filters = $this->resolver->resolve($user, Request::create('/?project='.$a->getUuid()));

        self::assertSame($a, $filters->project);
        self::assertSame([$a], $filters->selectedProjects);
    }

    private function project(string $name): Project
    {
        $project = new Project();
        $project->setSlug(strtolower($name));
        $project->setName($name);

        return $project;
    }
}
