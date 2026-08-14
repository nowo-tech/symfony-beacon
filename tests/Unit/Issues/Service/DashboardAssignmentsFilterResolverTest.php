<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\AssignmentScope;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\Service\DashboardAssignmentsFilterResolver;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DashboardAssignmentsFilterResolverTest extends TestCase
{
    /** @var list<Project> */
    private array $projects = [];

    private ProjectMembershipRepository&Stub $membershipRepository;
    private DashboardAssignmentsFilterResolver $resolver;

    protected function setUp(): void
    {
        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('findAccessibleByUser')->willReturnCallback(
            fn (): array => $this->projects,
        );

        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->resolver = new DashboardAssignmentsFilterResolver(
            new AccessibleProjectsProvider($projectRepository, new RequestStack()),
            $this->membershipRepository,
        );
    }

    public function testDefaultsWhenQueryEmpty(): void
    {
        $viewer = $this->user(1, 'viewer@example.com');
        $project = $this->project('One');
        $teammate = $this->user(2, 'mate@example.com', 'Mate');
        $this->projects = [$project];
        $this->membershipRepository->method('findUsersByProjects')->willReturn([$viewer, $teammate]);

        $filters = $this->resolver->resolve($viewer, Request::create('/dashboard/assignments'));

        self::assertSame([$project], $filters->accessibleProjects);
        self::assertSame([$project], $filters->selectedProjects);
        self::assertSame([$teammate], $filters->teammates);
        self::assertSame(AssignmentScope::Mine, $filters->scope);
        self::assertNull($filters->project);
        self::assertNull($filters->query);
        self::assertNull($filters->level);
        self::assertSame(IssueStatus::Unresolved, $filters->status);
        self::assertNull($filters->priority);
        self::assertNull($filters->assignee);
        self::assertSame('last_seen', $filters->sort->field);
    }

    public function testResolvesProjectScopeStatusPriorityAndAssignee(): void
    {
        $viewer = $this->user(1, 'viewer@example.com');
        $a = $this->project('A');
        $b = $this->project('B');
        $teammate = $this->user(7, 'mate@example.com', 'Mate');
        $this->projects = [$a, $b];
        $this->membershipRepository->method('findUsersByProjects')->willReturn([$viewer, $teammate]);

        $filters = $this->resolver->resolve($viewer, Request::create('/', Request::METHOD_GET, [
            'project' => $b->getUuid(),
            'scope' => 'teammates',
            'status' => IssueStatus::Resolved->value,
            'priority' => IssuePriority::High->value,
            'assignee' => '7',
            'q' => 'boom',
            'level' => 'error',
            'sort' => 'title',
            'dir' => 'asc',
        ]));

        self::assertSame($b, $filters->project);
        self::assertSame([$b], $filters->selectedProjects);
        self::assertSame(AssignmentScope::Teammates, $filters->scope);
        self::assertSame(IssueStatus::Resolved, $filters->status);
        self::assertSame(IssuePriority::High, $filters->priority);
        self::assertSame($teammate, $filters->assignee);
        self::assertSame('boom', $filters->query);
        self::assertSame('error', $filters->level);
        self::assertSame('title', $filters->sort->field);
        self::assertSame('asc', $filters->sort->direction);
    }

    public function testInvalidStatusPriorityAndAssigneeFallBack(): void
    {
        $viewer = $this->user(1, 'viewer@example.com');
        $project = $this->project('One');
        $teammate = $this->user(2, 'mate@example.com');
        $this->projects = [$project];
        $this->membershipRepository->method('findUsersByProjects')->willReturn([$teammate]);

        $filters = $this->resolver->resolve($viewer, Request::create('/', Request::METHOD_GET, [
            'status' => 'not-a-status',
            'priority' => 'not-a-priority',
            'assignee' => 'abc',
            'scope' => 'nope',
        ]));

        self::assertSame(IssueStatus::Unresolved, $filters->status);
        self::assertNull($filters->priority);
        self::assertNull($filters->assignee);
        self::assertSame(AssignmentScope::Mine, $filters->scope);

        $unknownAssignee = $this->resolver->resolve($viewer, Request::create('/?assignee=99'));
        self::assertNull($unknownAssignee->assignee);
    }

    private function project(string $name): Project
    {
        $project = new Project();
        $project->setSlug(strtolower($name));
        $project->setName($name);

        return $project;
    }

    private function user(int $id, string $email, string $displayName = ''): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName);
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
