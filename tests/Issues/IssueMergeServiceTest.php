<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Service\IssueMergeService;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Shared\IssueStatus;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final class IssueMergeServiceTest extends DatabaseWebTestCase
{
    public function testRejectsLongerDuplicateCycle(): void
    {
        [, $user, $project] = $this->bootWithDemoProject('merge-cycle@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $a = $this->makeIssue($project, 'cycle-a');
        $b = $this->makeIssue($project, 'cycle-b');
        $c = $this->makeIssue($project, 'cycle-c');
        $em->persist($a);
        $em->persist($b);
        $em->persist($c);
        $em->flush();

        $b->setDuplicateOf($a);
        $c->setDuplicateOf($b);
        $em->flush();

        $merge = self::getContainer()->get(IssueMergeService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('circular');
        $merge->assertCanMarkAsDuplicate($a, $c);
    }

    public function testAllowsSameEventIdAcrossProjects(): void
    {
        [, $user, $projectA] = $this->bootWithDemoProject('event-id-a@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $projectB = new Project();
        $projectB->setName('Other');
        $projectB->setSlug('other-event-id');
        $em->persist($projectB);
        $membership = new ProjectMembership();
        $membership->setProject($projectB);
        $membership->setUser($user);
        $membership->setRole(ProjectRole::Owner);
        $em->persist($membership);

        $issueA = $this->makeIssue($projectA, 'fp-a');
        $issueB = $this->makeIssue($projectB, 'fp-b');
        $em->persist($issueA);
        $em->persist($issueB);
        $em->flush();

        $sharedId = 'shared-client-event-id';
        $eventA = new Event();
        $eventA->setIssue($issueA);
        $eventA->setEventId($sharedId);
        $eventA->setPayload(['message' => 'a']);
        $eventA->setReceivedAt(new DateTimeImmutable());
        $eventA->setEventTimestamp(new DateTimeImmutable());
        $em->persist($eventA);

        $eventB = new Event();
        $eventB->setIssue($issueB);
        $eventB->setEventId($sharedId);
        $eventB->setPayload(['message' => 'b']);
        $eventB->setReceivedAt(new DateTimeImmutable());
        $eventB->setEventTimestamp(new DateTimeImmutable());
        $em->persist($eventB);
        $em->flush();

        self::assertNotNull($eventA->getId());
        self::assertNotNull($eventB->getId());
        self::assertSame($projectA->getId(), $eventA->getProject()?->getId());
        self::assertSame($projectB->getId(), $eventB->getProject()?->getId());
    }

    private function makeIssue(Project $project, string $fingerprint): Issue
    {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $fingerprint));
        $issue->setTitle($fingerprint);
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());

        return $issue;
    }
}
