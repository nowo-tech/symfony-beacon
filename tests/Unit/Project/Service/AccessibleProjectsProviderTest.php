<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AccessibleProjectsProviderTest extends TestCase
{
    private ProjectRepository&MockObject $projectRepository;
    private RequestStack $requestStack;
    private AccessibleProjectsProvider $provider;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->requestStack = new RequestStack();
        $this->provider = new AccessibleProjectsProvider($this->projectRepository, $this->requestStack);
    }

    public function testWithoutRequestAlwaysHitsRepository(): void
    {
        $user = $this->user(1);
        $projects = [$this->project('A')];
        $this->projectRepository
            ->expects(self::exactly(2))
            ->method('findAccessibleByUser')
            ->with($user, null)
            ->willReturn($projects);

        self::assertSame($projects, $this->provider->forUser($user));
        self::assertSame($projects, $this->provider->forUser($user));
    }

    public function testCachesPerUserAndQueryOnCurrentRequest(): void
    {
        $user = $this->user(7);
        $projects = [$this->project('Cached')];
        $this->requestStack->push(Request::create('/'));

        $this->projectRepository
            ->expects(self::once())
            ->method('findAccessibleByUser')
            ->with($user, null)
            ->willReturn($projects);

        self::assertSame($projects, $this->provider->forUser($user));
        self::assertSame($projects, $this->provider->forUser($user));
        self::assertSame($projects, $this->provider->forUser($user, '   '));
    }

    public function testDifferentQueryBypassesCache(): void
    {
        $user = $this->user(3);
        $all = [$this->project('All')];
        $filtered = [$this->project('Filtered')];
        $this->requestStack->push(Request::create('/'));

        $this->projectRepository
            ->expects(self::exactly(2))
            ->method('findAccessibleByUser')
            ->willReturnCallback(static function (User $u, ?string $query) use ($user, $all, $filtered): array {
                self::assertSame($user, $u);

                return null === $query ? $all : $filtered;
            });

        self::assertSame($all, $this->provider->forUser($user));
        self::assertSame($filtered, $this->provider->forUser($user, '  beacon '));
    }

    public function testUserWithoutIdSkipsCache(): void
    {
        $user = new User();
        $projects = [$this->project('Anon')];
        $this->requestStack->push(Request::create('/'));
        $this->projectRepository
            ->expects(self::exactly(2))
            ->method('findAccessibleByUser')
            ->with($user, null)
            ->willReturn($projects);

        self::assertSame($projects, $this->provider->forUser($user));
        self::assertSame($projects, $this->provider->forUser($user));
    }

    private function user(int $id): User
    {
        $user = new User();
        (new ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private function project(string $name): Project
    {
        $project = new Project();
        $project->setSlug(strtolower($name));
        $project->setName($name);

        return $project;
    }
}
