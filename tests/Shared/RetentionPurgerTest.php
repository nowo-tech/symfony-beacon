<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
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

        $purger = new RetentionPurger($em, self::getContainer()->get(ProjectRepository::class), 30, 0);
        $result = $purger->purgeProject($project);

        self::assertGreaterThanOrEqual(1, $result['events']);
        self::assertNull($em->getRepository(Event::class)->findOneBy(['eventId' => $oldEvent->getEventId()]));
        self::assertNotNull($em->getRepository(Event::class)->findOneBy(['eventId' => $newEvent->getEventId()]));
    }
}
