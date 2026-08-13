<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\Service\IssueIndexFilterResolver;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;

final class IssueIndexFilterResolverTest extends TestCase
{
    private ProjectMembershipRepository&Stub $membershipRepository;
    private IssueIndexFilterResolver $resolver;

    protected function setUp(): void
    {
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->resolver = new IssueIndexFilterResolver($this->membershipRepository);
    }

    public function testDefaultsAndMemberAssignee(): void
    {
        $project = $this->project();
        $member = $this->user(5, 'member@example.com', 'Member');
        $this->membershipRepository->method('findUsersByProject')->willReturn([$member]);

        $filters = $this->resolver->resolve($project, Request::create('/', Request::METHOD_GET, [
            'assignee' => '5',
            'status' => IssueStatus::Resolved->value,
            'priority' => IssuePriority::Medium->value,
            'q' => 'timeout',
            'environment' => 'staging',
        ]));

        self::assertSame([$member], $filters->members);
        self::assertSame($member, $filters->assignee);
        self::assertFalse($filters->unassignedOnly);
        self::assertSame('5', $filters->assigneeFilter);
        self::assertSame(IssueStatus::Resolved, $filters->status);
        self::assertSame(IssuePriority::Medium, $filters->priority);
        self::assertSame('timeout', $filters->query);
        self::assertSame('staging', $filters->environment);
    }

    public function testUnassignedAndInvalidFallbacks(): void
    {
        $project = $this->project();
        $this->membershipRepository->method('findUsersByProject')->willReturn([]);

        $filters = $this->resolver->resolve($project, Request::create('/', Request::METHOD_GET, [
            'assignee' => 'unassigned',
            'status' => 'nope',
            'priority' => 'nope',
        ]));

        self::assertTrue($filters->unassignedOnly);
        self::assertNull($filters->assignee);
        self::assertSame('unassigned', $filters->assigneeFilter);
        self::assertSame(IssueStatus::Unresolved, $filters->status);
        self::assertNull($filters->priority);
    }

    public function testUnknownAssigneeIdLeavesAssigneeNull(): void
    {
        $project = $this->project();
        $member = $this->user(1, 'a@example.com');
        $this->membershipRepository->method('findUsersByProject')->willReturn([$member]);

        $filters = $this->resolver->resolve($project, Request::create('/?assignee=99'));

        self::assertNull($filters->assignee);
        self::assertFalse($filters->unassignedOnly);
        self::assertSame('99', $filters->assigneeFilter);
    }

    private function project(): Project
    {
        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');

        return $project;
    }

    private function user(int $id, string $email, string $displayName = ''): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName);
        (new ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
