<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Service\DashboardMentionsFilterResolver;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DashboardMentionsFilterResolverTest extends TestCase
{
    /** @var list<Project> */
    private array $projects = [];

    private DashboardMentionsFilterResolver $resolver;

    protected function setUp(): void
    {
        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('findAccessibleByUser')->willReturnCallback(
            fn (): array => $this->projects,
        );
        $this->resolver = new DashboardMentionsFilterResolver(
            new AccessibleProjectsProvider($projectRepository, new RequestStack()),
        );
    }

    public function testDefaultsToAllAccessibleProjectsAndUnreadFalse(): void
    {
        $user = new User();
        $a = $this->project('A');
        $b = $this->project('B');
        $this->projects = [$a, $b];

        $filters = $this->resolver->resolve($user, Request::create('/dashboard/mentions'));

        self::assertSame([$a, $b], $filters->accessibleProjects);
        self::assertSame([$a, $b], $filters->selectedProjects);
        self::assertNull($filters->project);
        self::assertFalse($filters->unreadOnly);
    }

    public function testSelectedProjectAndUnreadFlag(): void
    {
        $user = new User();
        $a = $this->project('A');
        $b = $this->project('B');
        $this->projects = [$a, $b];

        $filters = $this->resolver->resolve($user, Request::create('/', Request::METHOD_GET, [
            'project' => $b->getUuid(),
            'unread' => '1',
        ]));

        self::assertSame($b, $filters->project);
        self::assertSame([$b], $filters->selectedProjects);
        self::assertTrue($filters->unreadOnly);
    }

    private function project(string $name): Project
    {
        $project = new Project();
        $project->setSlug(strtolower($name));
        $project->setName($name);

        return $project;
    }
}
