<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Dto;

use App\Identity\Entity\User;
use App\Issues\AssignmentScope;
use App\Issues\Dto\DashboardAssignmentsFilters;
use App\Issues\Dto\DashboardMentionsFilters;
use App\Issues\Dto\DashboardNewInReleaseFilters;
use App\Issues\Dto\IssueIndexFilters;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\IssueListSort;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class DashboardFilterDtoTest extends TestCase
{
    public function testAssignmentsTeammateChoicesSkipsUsersWithoutIdAndPreferDisplayName(): void
    {
        $withName = $this->user(1, 'alice@example.com', 'Alice');
        $emailOnly = $this->user(2, 'bob@example.com', '');
        $noId = $this->user(null, 'ghost@example.com', 'Ghost');

        $filters = $this->assignments(teammates: [$withName, $emailOnly, $noId]);

        self::assertSame(
            [
                1 => 'Alice',
                2 => 'bob@example.com',
            ],
            $filters->teammateChoices(),
        );
    }

    public function testAssignmentsFormDataAndProjectChoices(): void
    {
        $project = $this->project('Alpha');
        $assignee = $this->user(9, 'owner@example.com', 'Owner');

        $filters = $this->assignments(
            accessible: [$project],
            project: $project,
            query: 'crash',
            level: 'error',
            status: IssueStatus::Resolved,
            priority: IssuePriority::High,
            assignee: $assignee,
            sort: new IssueListSort('title', 'asc'),
        );

        self::assertSame(
            [
                'scope' => 'mine',
                'project' => $project->getUuid(),
                'q' => 'crash',
                'level' => 'error',
                'status' => IssueStatus::Resolved->value,
                'priority' => IssuePriority::High->value,
                'assignee' => '9',
                'sort' => 'title',
                'dir' => 'asc',
                'page' => 1,
                'per_page' => 25,
            ],
            $filters->formData(25),
        );
        self::assertSame(['Alpha' => $project->getUuid()], $filters->projectChoices());
    }

    public function testAssignmentsFormDataUsesEmptyDefaultsWhenOptionalFieldsMissing(): void
    {
        $filters = $this->assignments();

        self::assertSame(
            [
                'scope' => 'mine',
                'project' => '',
                'q' => '',
                'level' => '',
                'status' => IssueStatus::Unresolved->value,
                'priority' => '',
                'assignee' => '',
                'sort' => IssueListSort::DEFAULT_FIELD,
                'dir' => IssueListSort::DEFAULT_DIRECTION,
                'page' => 1,
                'per_page' => 50,
            ],
            $filters->formData(50),
        );
    }

    public function testMentionsFormDataAndRedirectQueryFilterEmptyValues(): void
    {
        $project = $this->project('Mentions');
        $filters = new DashboardMentionsFilters([$project], [$project], $project, true);

        self::assertSame(
            [
                'project' => $project->getUuid(),
                'unread' => true,
                'page' => 1,
                'per_page' => 20,
            ],
            $filters->formData(20),
        );
        self::assertSame(
            [
                'project' => $project->getUuid(),
                'unread' => '1',
                'per_page' => '20',
            ],
            $filters->redirectQuery(20),
        );

        $empty = new DashboardMentionsFilters([], [], null, false);
        self::assertSame(['per_page' => '10'], $empty->redirectQuery(10));
    }

    public function testNewInReleaseChoicesAndFormData(): void
    {
        $project = $this->project('Release');
        $filters = new DashboardNewInReleaseFilters(
            [$project],
            [$project],
            ['1.0.0', '1.1.0'],
            $project,
            '1.1.0',
        );

        self::assertSame(
            [
                '1.0.0' => '1.0.0',
                '1.1.0' => '1.1.0',
            ],
            $filters->releaseChoices(),
        );
        self::assertSame(
            [
                'project' => $project->getUuid(),
                'release' => '1.1.0',
                'page' => 1,
                'per_page' => 15,
            ],
            $filters->formData(15),
        );

        $empty = new DashboardNewInReleaseFilters([], [], [], null, null);
        self::assertSame(
            [
                'project' => '',
                'release' => '',
                'page' => 1,
                'per_page' => 15,
            ],
            $empty->formData(15),
        );
    }

    public function testIssueIndexMemberChoicesAndFormData(): void
    {
        $member = $this->user(3, 'member@example.com', 'Member');
        $filters = new IssueIndexFilters(
            members: [$member, $this->user(null, 'skip@example.com', 'Skip')],
            query: 'oom',
            level: 'fatal',
            status: IssueStatus::Ignored,
            priority: IssuePriority::Low,
            environment: 'prod',
            release: '2.0.0',
            compare: '1.9.0',
            tag: 'backend',
            url: '/api',
            user: 'uid-1',
            assignee: $member,
            unassignedOnly: false,
            assigneeFilter: '3',
            sort: new IssueListSort('events', 'desc'),
        );

        self::assertSame([3 => 'Member'], $filters->memberChoices());
        self::assertSame(
            [
                'q' => 'oom',
                'level' => 'fatal',
                'status' => IssueStatus::Ignored->value,
                'environment' => 'prod',
                'release' => '2.0.0',
                'compare' => '1.9.0',
                'tag' => 'backend',
                'url' => '/api',
                'user' => 'uid-1',
                'priority' => IssuePriority::Low->value,
                'assignee' => '3',
                'sort' => 'events',
                'dir' => 'desc',
                'page' => 2,
                'per_page' => 40,
            ],
            $filters->formData(2, 40),
        );
    }

    /**
     * @param list<Project> $accessible
     * @param list<User>    $teammates
     */
    private function assignments(
        array $accessible = [],
        ?Project $project = null,
        array $teammates = [],
        ?string $query = null,
        ?string $level = null,
        IssueStatus $status = IssueStatus::Unresolved,
        ?IssuePriority $priority = null,
        ?User $assignee = null,
        ?IssueListSort $sort = null,
    ): DashboardAssignmentsFilters {
        return new DashboardAssignmentsFilters(
            accessibleProjects: $accessible,
            selectedProjects: $project instanceof Project ? [$project] : $accessible,
            teammates: $teammates,
            scope: AssignmentScope::Mine,
            project: $project,
            query: $query,
            level: $level,
            status: $status,
            priority: $priority,
            assignee: $assignee,
            sort: $sort ?? IssueListSort::fromQuery(null, null),
        );
    }

    private function project(string $name): Project
    {
        $project = new Project();
        $project->setSlug(strtolower($name));
        $project->setName($name);

        return $project;
    }

    private function user(?int $id, string $email, string $displayName): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName);
        if (null !== $id) {
            new ReflectionProperty(User::class, 'id')->setValue($user, $id);
        }

        return $user;
    }
}
