<?php

declare(strict_types=1);

namespace App\Tests\Integration\Issues;

use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Issues\Entity\IssueMention;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueMentionRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class IssueRepositoryQueriesTest extends DatabaseWebTestCase
{
    public function testEventRepositoryQueryHelpers(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('events-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(EventRepository::class);
        $now = new DateTimeImmutable('now');
        $recentAt = $now->modify('-1 hour');
        $weekAt = $now->modify('-4 days');
        $oldAt = $now->modify('-40 days');
        $searchAt = $now->modify('-1 day');
        $otherAt = $now->modify('-2 hours');

        $issue = $this->issue($project, 'Event repo issue', 'error', IssueStatus::Unresolved);
        $secondIssue = $this->issue($project, 'Searchable culprit', 'fatal', IssueStatus::Resolved);
        $otherProject = (new Project())->setName('Elsewhere')->setSlug('elsewhere-events');
        $emptyProject = (new Project())->setName('No Events')->setSlug('no-events');
        $em->persist($issue);
        $em->persist($secondIssue);
        $em->persist($otherProject);
        $em->persist($emptyProject);
        $em->flush();

        $otherIssue = $this->issue($otherProject, 'Other issue', 'error', IssueStatus::Unresolved);
        $em->persist($otherIssue);
        $em->flush();

        $recent = $this->event($issue, 'evt-recent', 'production', '1.0.0', $recentAt, '/orders');
        $week = $this->event($issue, 'evt-week', 'production', '1.0.1', $weekAt, '/orders/1');
        $old = $this->event($issue, 'evt-old', 'staging', null, $oldAt, '/legacy');
        $search = $this->event($secondIssue, 'search-123', 'production', '2.0.0', $searchAt, '/search');
        $other = $this->event($otherIssue, 'other-evt', 'production', '9.9.9', $otherAt, '/outside');
        $issue->incrementEventCount()->incrementEventCount();
        $em->persist($recent);
        $em->persist($week);
        $em->persist($old);
        $em->persist($search);
        $em->persist($other);
        $em->flush();

        self::assertSame([$recent, $week], $repository->findLatestForIssue($issue, 2));
        self::assertSame($recent->getId(), $repository->findOneByEventId('evt-recent')?->getId());
        self::assertSame($recent->getId(), $repository->findOneByProjectAndEventId($project, 'evt-recent')?->getId());
        self::assertNull($repository->findOneByProjectAndEventId($otherProject, 'evt-recent'));

        $stats = $repository->occurrenceStatsForIssue($issue, $now);
        self::assertSame(3, $stats->total);
        self::assertSame(1, $stats->last24h);
        self::assertSame(2, $stats->last7d);
        self::assertSame(2, $stats->last30d);
        self::assertSame([], $repository->occurrenceStatsForIssues([new Issue()], $now));

        self::assertSame(1, $repository->countReceivedTodayForProject($project));
        self::assertSame(3, $repository->countReceivedSinceForProject($project, $now->modify('-5 days')));
        self::assertSame(
            [(int) $project->getId() => 3, (int) $otherProject->getId() => 1],
            $repository->countReceivedSinceForProjectIds(
                [(int) $project->getId(), (int) $otherProject->getId()],
                $now->modify('-5 days'),
            ),
        );
        self::assertSame([], $repository->countReceivedSinceForProjectIds([], new DateTimeImmutable('2026-08-15 00:00:00')));

        self::assertSame(
            ['1.0.0', '2.0.0'],
            $repository->findDistinctReleaseVersions($project, 2),
        );
        self::assertSame([], $repository->findDistinctReleaseVersions(new Project(), 5));

        self::assertSame(
            $recentAt->format('Y-m-d H:i:s'),
            $repository->findLastReceivedAtForProject($project)?->format('Y-m-d H:i:s'),
        );
        self::assertNull($repository->findLastReceivedAtForProject($emptyProject));
        self::assertSame(
            [$recentAt->format('Y-m-d H:i:s'), $otherAt->format('Y-m-d H:i:s')],
            array_values(array_map(
                static fn (DateTimeImmutable $at): string => $at->format('Y-m-d H:i:s'),
                $repository->findLastReceivedAtForProjectIds([(int) $project->getId(), (int) $otherProject->getId()]),
            )),
        );
        self::assertSame([], $repository->findLastReceivedAtForProjectIds([]));

        self::assertSame(
            3,
            $repository->countReceivedSince(
                $project,
                $now->modify('-5 days'),
                'production',
                null,
            ),
        );
        self::assertCount(
            1,
            $repository->searchForExport($project, 'search', 'fatal', IssueStatus::Resolved, 'production', '2.0.0', 10),
        );
        self::assertSame(
            [
                $weekAt->format('Y-m-d') => 1,
                $searchAt->format('Y-m-d') => 1,
                $recentAt->format('Y-m-d') => 1,
            ],
            $repository->countErrorsByDay(
                $project,
                $now->modify('-5 days'),
                $now,
                'production',
                null,
                null,
            ),
        );
    }

    public function testIssueMentionRepositoryQueries(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('mentions-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $repository = self::getContainer()->get(IssueMentionRepository::class);

        $member = (new User())
            ->setEmail('mentioned@example.com')
            ->setDisplayName('Mentioned');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $other = (new User())
            ->setEmail('other-mentioned@example.com')
            ->setDisplayName('Other Mentioned');
        $other->setPassword($hasher->hashPassword($other, 'secret'));
        $membership = (new ProjectMembership())
            ->setUser($member)
            ->setRole(ProjectRole::Member);
        $project->addMembership($membership);

        $issue = $this->issue($project, 'Mention issue', 'error', IssueStatus::Unresolved);
        $comment = (new IssueComment())
            ->setIssue($issue)
            ->setAuthor($owner)
            ->setBody('hello @mentioned');
        $mentionUnread = (new IssueMention())
            ->setComment($comment)
            ->setMentionedUser($member);
        $mentionRead = (new IssueMention())
            ->setComment($comment)
            ->setMentionedUser($other)
            ->markRead(new DateTimeImmutable('2026-08-16 12:00:00'));

        $em->persist($member);
        $em->persist($other);
        $em->persist($issue);
        $em->persist($comment);
        $em->persist($mentionUnread);
        $em->persist($mentionRead);
        $em->flush();

        self::assertSame([], $repository->findInboxForUser($member, []));
        self::assertCount(1, $repository->findInboxForUser($member, [$project], unreadOnly: true, limit: 10));
        self::assertSame(1, $repository->countInboxForUser($member, [$project], unreadOnly: true));
        self::assertSame(0, $repository->countInboxForUser($member, []));
        self::assertSame($mentionUnread->getId(), $repository->findOneForUser($member, (int) $mentionUnread->getId())?->getId());
        self::assertTrue($repository->isUserMentionedOnIssue($member, $issue));
        self::assertSame(
            [(int) $member->getId(), (int) $other->getId()],
            $repository->findUserIdsMentionedOnIssue($issue, [(int) $member->getId(), (int) $other->getId(), (int) $other->getId()]),
        );
        self::assertSame([], $repository->findUserIdsMentionedOnIssue($issue, []));
        self::assertSame(1, $repository->markAllReadForUser($member, [$project]));
        self::assertFalse($repository->findOneForUser($member, (int) $mentionUnread->getId())?->isUnread());
        self::assertSame(0, $repository->markAllReadForUser($member, []));
    }

    private function issue(Project $project, string $title, string $level, IssueStatus $status): Issue
    {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $title));
        $issue->setTitle($title);
        $issue->setCulprit($title.' culprit');
        $issue->setLevel($level);
        $issue->setStatus($status);
        $issue->setFirstSeen(new DateTimeImmutable('2026-08-01 00:00:00'));
        $issue->setLastSeen(new DateTimeImmutable('2026-08-16 00:00:00'));
        $issue->incrementEventCount();

        return $issue;
    }

    private function event(
        Issue $issue,
        string $eventId,
        ?string $environment,
        ?string $release,
        DateTimeImmutable $receivedAt,
        ?string $requestUrl,
    ): Event {
        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId($eventId);
        $event->setEnvironment($environment);
        $event->setReleaseVersion($release);
        $event->setRequestUrl($requestUrl);
        $event->setPayload(['id' => $eventId]);
        $event->setEventTimestamp($receivedAt);
        $event->setReceivedAt($receivedAt);

        return $event;
    }
}
