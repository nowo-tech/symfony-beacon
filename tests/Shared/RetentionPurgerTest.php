<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Service\IssueMergeService;
use App\Project\Repository\ProjectRepository;
use App\Shared\IssueStatus;
use App\Shared\Retention\RetentionPurger;
use App\Tests\Shared\DatabaseWebTestCase as BaseDatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class RetentionPurgerTest extends BaseDatabaseWebTestCase
{
    public function testPurgesEventsOlderThanRetentionDays(): void
    {
        [, , $project] = $this->bootWithDemoProject('retention@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $oldIssue = new Issue();
        $oldIssue->setProject($project);
        $oldIssue->setFingerprint('old-fp');
        $oldIssue->setTitle('Old');
        $oldIssue->setLevel('error');
        $oldIssue->setStatus(IssueStatus::Unresolved);
        $oldIssue->setFirstSeen(new DateTimeImmutable('-40 days'));
        $oldIssue->setLastSeen(new DateTimeImmutable('-40 days'));
        $em->persist($oldIssue);

        $oldEvent = new Event();
        $oldEvent->setIssue($oldIssue);
        $oldEvent->setEventId(bin2hex(random_bytes(8)));
        $oldEvent->setPayload(['message' => 'old']);
        $oldEvent->setReceivedAt(new DateTimeImmutable('-40 days'));
        $oldEvent->setEventTimestamp(new DateTimeImmutable('-40 days'));
        $em->persist($oldEvent);

        $newIssue = new Issue();
        $newIssue->setProject($project);
        $newIssue->setFingerprint('new-fp');
        $newIssue->setTitle('New');
        $newIssue->setLevel('error');
        $newIssue->setStatus(IssueStatus::Unresolved);
        $newIssue->setFirstSeen(new DateTimeImmutable('-1 day'));
        $newIssue->setLastSeen(new DateTimeImmutable('-1 day'));
        $em->persist($newIssue);

        $newEvent = new Event();
        $newEvent->setIssue($newIssue);
        $newEvent->setEventId(bin2hex(random_bytes(8)));
        $newEvent->setPayload(['message' => 'new']);
        $newEvent->setReceivedAt(new DateTimeImmutable('-1 day'));
        $newEvent->setEventTimestamp(new DateTimeImmutable('-1 day'));
        $em->persist($newEvent);
        $em->flush();

        $purger = new RetentionPurger(
            $em,
            self::getContainer()->get(ProjectRepository::class),
            self::getContainer()->get(IssueMergeService::class),
            30,
            0,
        );
        $result = $purger->purgeProject($project);

        self::assertGreaterThanOrEqual(1, $result['events']);
        self::assertNull($em->getRepository(Event::class)->findOneBy(['eventId' => $oldEvent->getEventId()]));
        self::assertNotNull($em->getRepository(Event::class)->findOneBy(['eventId' => $newEvent->getEventId()]));
    }

    public function testRecomputesEventCountAfterPartialPurge(): void
    {
        [, , $project] = $this->bootWithDemoProject('retention-recount@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('recount-fp');
        $issue->setTitle('Recount');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setFirstSeen(new DateTimeImmutable('-40 days'));
        $issue->setLastSeen(new DateTimeImmutable('-1 day'));
        $issue->setEventCount(2);
        $em->persist($issue);

        $old = new Event();
        $old->setIssue($issue);
        $old->setEventId('old-recount');
        $old->setPayload(['message' => 'old']);
        $old->setReceivedAt(new DateTimeImmutable('-40 days'));
        $old->setEventTimestamp(new DateTimeImmutable('-40 days'));
        $em->persist($old);

        $fresh = new Event();
        $fresh->setIssue($issue);
        $fresh->setEventId('fresh-recount');
        $fresh->setPayload(['message' => 'fresh']);
        $fresh->setReceivedAt(new DateTimeImmutable('-1 day'));
        $fresh->setEventTimestamp(new DateTimeImmutable('-1 day'));
        $em->persist($fresh);
        $em->flush();
        $issueId = $issue->getId();

        $purger = new RetentionPurger(
            $em,
            self::getContainer()->get(ProjectRepository::class),
            self::getContainer()->get(IssueMergeService::class),
            30,
            0,
        );
        $purger->purgeProject($project);

        $reloaded = $em->getRepository(Issue::class)->find($issueId);
        self::assertInstanceOf(Issue::class, $reloaded);
        self::assertSame(1, $reloaded->getEventCount());
        self::assertNull($em->getRepository(Event::class)->findOneBy(['eventId' => 'old-recount']));
        self::assertNotNull($em->getRepository(Event::class)->findOneBy(['eventId' => 'fresh-recount']));
    }
}
