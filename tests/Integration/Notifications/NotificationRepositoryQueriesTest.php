<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notifications;

use App\Identity\Entity\User;
use App\Notifications\Entity\MemberProjectAlertEvent;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Enum\MemberAlertScope;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Project\Entity\Project;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class NotificationRepositoryQueriesTest extends DatabaseWebTestCase
{
    public function testDigestBufferQueriesAndRemoval(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('notif-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(NotificationDigestBufferRepository::class);

        $otherProject = (new Project())
            ->setName('Other project')
            ->setSlug('other-project');
        $em->persist($otherProject);

        $first = (new NotificationDestination())
            ->setProject($project)
            ->setLabel('Slack')
            ->setType(NotificationDestinationType::Slack)
            ->setEndpointUrl('https://hooks.slack.com/services/T00/B00/AAA')
            ->setCategories(['error']);
        $second = (new NotificationDestination())
            ->setProject($otherProject)
            ->setLabel('HTTP')
            ->setType(NotificationDestinationType::Http)
            ->setEndpointUrl('https://example.test/hook')
            ->setCategories(['warning']);

        $project->addNotificationDestination($first);
        $otherProject->addNotificationDestination($second);
        $em->persist($first);
        $em->persist($second);
        $em->flush();

        $row1 = $repository->buffer($first, ['summary' => 'first']);
        $row2 = $repository->buffer($first, ['summary' => 'second']);
        $row3 = $repository->buffer($second, ['summary' => 'third']);
        $em->flush();

        $rows = $repository->findForDestination($first);
        self::assertCount(2, $rows);
        self::assertSame(['first', 'second'], array_map(
            static fn ($row): string => (string) ($row->getPayload()['summary'] ?? ''),
            $rows,
        ));

        $destinations = $repository->findDestinationsWithBufferedItems();
        self::assertCount(2, $destinations);
        self::assertSame([$first->getId(), $second->getId()], array_map(
            static fn (NotificationDestination $destination): ?int => $destination->getId(),
            $destinations,
        ));

        $repository->removeAll([$row1, $row2, $row3]);
        $em->flush();
        self::assertSame([], $repository->findForDestination($first));
        self::assertSame([], $repository->findDestinationsWithBufferedItems());

        unset($owner);
    }

    public function testMemberProjectAlertEventIndexesAndDeletion(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('alerts-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $repository = self::getContainer()->get(MemberProjectAlertEventRepository::class);

        $otherProject = (new Project())
            ->setName('Other alerts')
            ->setSlug('other-alerts');
        $member = (new User())
            ->setEmail('alerts-member@example.com')
            ->setDisplayName('Alerts Member')
            ->setPassword($hasher->hashPassword(new User(), 'secret'));
        $em->persist($otherProject);
        $em->persist($member);
        $em->flush();

        $ownerAssigned = (new MemberProjectAlertEvent())
            ->setUser($owner)
            ->setProject($project)
            ->setEvent(MemberAlertEvent::IssueAssigned)
            ->setEnabled(false)
            ->setScope(MemberAlertScope::Involved);
        $ownerCommented = (new MemberProjectAlertEvent())
            ->setUser($owner)
            ->setProject($otherProject)
            ->setEvent(MemberAlertEvent::IssueCommented);
        $memberResolved = (new MemberProjectAlertEvent())
            ->setUser($member)
            ->setProject($project)
            ->setEvent(MemberAlertEvent::IssueResolved);
        $em->persist($ownerAssigned);
        $em->persist($ownerCommented);
        $em->persist($memberResolved);
        $em->flush();

        $byEvent = $repository->findIndexedByEventForUserAndProject($owner, $project);
        self::assertArrayHasKey(MemberAlertEvent::IssueAssigned->value, $byEvent);
        self::assertFalse($byEvent[MemberAlertEvent::IssueAssigned->value]->isEnabled());

        self::assertSame([], $repository->findIndexedByProjectIdForUser($owner, []));
        $byProject = $repository->findIndexedByProjectIdForUser($owner, [$project, $otherProject]);
        self::assertArrayHasKey((int) $project->getId(), $byProject);
        self::assertArrayHasKey((int) $otherProject->getId(), $byProject);

        self::assertSame([], $repository->findIndexedByUserIdsForProject($project, []));
        $byUser = $repository->findIndexedByUserIdsForProject($project, [
            (int) $owner->getId(),
            (int) $member->getId(),
            (int) $member->getId(),
        ]);
        self::assertArrayHasKey((int) $owner->getId(), $byUser);
        self::assertArrayHasKey((int) $member->getId(), $byUser);

        $one = $repository->findOneByUserProjectAndEvent($owner, $project, MemberAlertEvent::IssueAssigned);
        self::assertInstanceOf(MemberProjectAlertEvent::class, $one);
        self::assertSame(MemberAlertScope::Involved, $one->getScope());

        $repository->deleteAllForUserAndProject($owner, $project);
        $em->clear();
        self::assertNull($repository->findOneByUserProjectAndEvent($owner, $project, MemberAlertEvent::IssueAssigned));
        self::assertInstanceOf(
            MemberProjectAlertEvent::class,
            $repository->findOneByUserProjectAndEvent($member, $project, MemberAlertEvent::IssueResolved),
        );
    }
}
