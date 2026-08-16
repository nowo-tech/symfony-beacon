<?php

declare(strict_types=1);

namespace App\Tests\Integration\Issues;

use App\Identity\Entity\User;
use App\Issues\AssignmentScope;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\IssueListSort;
use App\Issues\Repository\IssueSearchRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionProperty;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class IssueSearchRepositoryTest extends DatabaseWebTestCase
{
    public function testReleaseQueriesSearchAndAssignmentScopes(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('search-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $repository = self::getContainer()->get(IssueSearchRepository::class);

        $teammate = (new User())
            ->setEmail('search-teammate@example.com')
            ->setDisplayName('Teammate');
        $teammate->setPassword($hasher->hashPassword($teammate, 'secret'));
        new ReflectionProperty(User::class, 'id')->setValue($teammate, 2001);

        $project->addMembership(
            (new ProjectMembership())
                ->setProject($project)
                ->setUser($teammate)
                ->setRole(ProjectRole::Member),
        );

        $otherProject = (new Project())
            ->setName('Other search')
            ->setSlug('other-search');
        $em->persist($teammate);
        $em->persist($otherProject);
        $em->flush();

        $paymentMine = $this->issue(
            $project,
            'search-payment-mine',
            'Payment timeout on checkout',
            IssueStatus::Unresolved,
            IssuePriority::Critical,
            '1.0.0',
            '1.0.1',
            'production',
            $owner,
            new DateTimeImmutable('-1 hour'),
        );
        $paymentTeammate = $this->issue(
            $project,
            'search-payment-teammate',
            'Payment timeout in cart',
            IssueStatus::Resolved,
            IssuePriority::High,
            '1.0.0',
            '1.0.0',
            'production',
            $teammate,
            new DateTimeImmutable('-2 hours'),
        );
        $unassigned = $this->issue(
            $project,
            'search-unassigned',
            'Payment timeout on API',
            IssueStatus::Unresolved,
            IssuePriority::Medium,
            '1.0.2',
            '1.0.2',
            'staging',
            null,
            new DateTimeImmutable('-3 hours'),
        );
        $ignored = $this->issue(
            $project,
            'search-ignored',
            'Payment timeout ignored',
            IssueStatus::Ignored,
            IssuePriority::Low,
            '1.0.0',
            '1.0.0',
            'production',
            null,
            new DateTimeImmutable('-4 hours'),
        );
        $otherProjectIssue = $this->issue(
            $otherProject,
            'search-other-project',
            'Payment timeout elsewhere',
            IssueStatus::Unresolved,
            IssuePriority::Medium,
            '2.0.0',
            '2.0.1',
            'production',
            null,
            new DateTimeImmutable('-30 minutes'),
        );

        $em->persist($paymentMine);
        $em->persist($paymentTeammate);
        $em->persist($unassigned);
        $em->persist($ignored);
        $em->persist($otherProjectIssue);
        $em->flush();

        $em->persist($this->event($paymentMine, 'evt-a', '/checkout', 'customer-42'));
        $em->persist($this->event($paymentTeammate, 'evt-b', '/cart', 'customer-11'));
        $em->persist($this->event($unassigned, 'evt-c', '/api/orders', 'customer-42'));
        $em->persist($this->event($otherProjectIssue, 'evt-d', '/checkout', 'other-user'));
        $em->flush();

        self::assertSame([], $repository->searchNewInRelease([]));
        self::assertSame(0, $repository->countNewInRelease([]));
        self::assertCount(3, $repository->searchNewInRelease([$project], ' 1.0.0 ', limit: 10, offset: 0));
        self::assertSame(3, $repository->countNewInRelease([$project], '1.0.0'));
        self::assertCount(3, $repository->searchNewInRelease([$project], null, limit: 10, offset: 1));

        self::assertSame(
            ['2.0.0', '1.0.0', '1.0.2'],
            $repository->findDistinctFirstReleasesAcrossProjects([$project, $otherProject], 10),
        );
        self::assertSame([], $repository->findDistinctFirstReleasesAcrossProjects([new Project()], 10));
        self::assertSame(['1.0.1', '1.0.0'], $repository->findDistinctReleases($project, 2));
        self::assertSame([], $repository->findDistinctReleases(new Project(), 5));

        self::assertSame(3, $repository->countNewIssuesByFirstRelease($project, ' 1.0.0 '));
        self::assertSame(0, $repository->countNewIssuesByFirstRelease($project, '   '));
        self::assertSame(['1.0.0' => 3, '1.0.2' => 1], $repository->countNewIssuesByFirstReleaseMap($project));
        self::assertCount(3, $repository->findByRelease($project, '1.0.0', 10));
        self::assertSame([], $repository->findByRelease($project, '   ', 10));
        self::assertCount(1, $repository->findLatestNewIssuesByFirstRelease($project, '1.0.0', 1));
        self::assertSame([], $repository->findLatestNewIssuesByFirstRelease($project, '', 1));

        $filtered = $repository->search(
            $project,
            query: 'Payment timeout',
            level: 'error',
            status: IssueStatus::Unresolved,
            environment: 'production',
            release: '1.0.0',
            priority: IssuePriority::Critical,
            assignee: $owner,
            unassignedOnly: false,
            sort: new IssueListSort('assignee', 'asc'),
            limit: 10,
            offset: 0,
            url: '/checkout',
            user: 'customer-42',
        );
        self::assertSame([$paymentMine->getId()], array_map(static fn (Issue $issue): ?int => $issue->getId(), $filtered));
        self::assertSame(
            1,
            $repository->countSearch(
                $project,
                query: 'Payment timeout',
                level: 'error',
                status: IssueStatus::Unresolved,
                environment: 'production',
                release: '1.0.0',
                priority: IssuePriority::Critical,
                assignee: $owner,
                unassignedOnly: false,
                url: '/checkout',
                user: 'customer-42',
            ),
        );
        self::assertCount(1, $repository->findByLastEnvironment($project, 'production', 1));
        self::assertSame([], $repository->findByLastEnvironment($project, '', 10));
        self::assertSame(2, $repository->countByProjectAndStatus($project, IssueStatus::Unresolved));
        self::assertSame(
            [(int) $project->getId() => 2, (int) $otherProject->getId() => 1],
            $repository->countByStatusForProjectIds([(int) $project->getId(), (int) $otherProject->getId()], IssueStatus::Unresolved),
        );
        self::assertSame([], $repository->countByStatusForProjectIds([], IssueStatus::Resolved));

        self::assertCount(1, $repository->searchAssignments([$project], AssignmentScope::Mine, $owner, 'Payment', 'error', null, null, null, new IssueListSort('last_seen', 'desc'), 10, 0));
        self::assertSame(1, $repository->countAssignments([$project], AssignmentScope::Mine, $owner, 'Payment', 'error'));
        self::assertCount(1, $repository->searchAssignments([$project], AssignmentScope::Teammates, $owner, 'Payment', null, null, null, null, new IssueListSort('title', 'asc'), 10, 0));
        self::assertCount(1, $repository->searchAssignments([$project], AssignmentScope::Teammates, $owner, null, null, null, null, $teammate, new IssueListSort('last_seen', 'desc'), 10, 0));
        self::assertCount(2, $repository->searchAssignments([$project], AssignmentScope::Unassigned, $owner, null, null, null, null, null, new IssueListSort('events_24h', 'desc'), 10, 0));
        self::assertCount(1, $repository->searchAssignments([$project], AssignmentScope::All, $owner, null, null, null, null, $owner, new IssueListSort('last_seen', 'desc'), 10, 0));
    }

    private function issue(
        Project $project,
        string $seed,
        string $title,
        IssueStatus $status,
        IssuePriority $priority,
        ?string $firstRelease,
        ?string $lastRelease,
        ?string $lastEnvironment,
        ?User $assignee,
        DateTimeImmutable $lastSeen,
    ): Issue {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $seed));
        $issue->setTitle($title);
        $issue->setCulprit($title.' culprit');
        $issue->setLevel('error');
        $issue->setStatus($status);
        $issue->setPriority($priority);
        $issue->setFirstSeen($lastSeen->modify('-1 day'));
        $issue->setLastSeen($lastSeen);
        $issue->setFirstRelease($firstRelease);
        $issue->setLastRelease($lastRelease);
        $issue->setLastEnvironment($lastEnvironment);
        $issue->setAssignee($assignee);
        $issue->incrementEventCount();

        return $issue;
    }

    private function event(Issue $issue, string $eventId, string $requestUrl, string $userIdentifier): Event
    {
        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId($eventId);
        $event->setRequestUrl($requestUrl);
        $event->setUserIdentifier($userIdentifier);
        $event->setPayload(['id' => $eventId]);
        $event->setReceivedAt(new DateTimeImmutable('-1 hour'));
        $event->setEventTimestamp(new DateTimeImmutable('-1 hour'));

        return $event;
    }
}
