<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notifications;

use App\Identity\Entity\User;
use App\Notifications\Entity\MemberAccountAlertEvent;
use App\Notifications\Entity\MemberProjectAlertEvent;
use App\Notifications\Entity\MemberProjectAlertPreference;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Enum\MemberAlertScope;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Project\Entity\Project;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class NotificationRepositoryQueriesTest extends DatabaseWebTestCase
{
    public function testDigestBufferQueriesAndRemoval(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('notif-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(NotificationDigestBufferRepository::class);

        $otherProject = new Project()
            ->setName('Other project')
            ->setSlug('other-project');
        $em->persist($otherProject);

        $first = new NotificationDestination()
            ->setProject($project)
            ->setLabel('Slack')
            ->setType(NotificationDestinationType::Slack)
            ->setEndpointUrl('https://hooks.slack.com/services/T00/B00/AAA')
            ->setCategories(['error']);
        $second = new NotificationDestination()
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

        $otherProject = new Project()
            ->setName('Other alerts')
            ->setSlug('other-alerts');
        $member = new User()
            ->setEmail('alerts-member@example.com')
            ->setDisplayName('Alerts Member')
            ->setPassword($hasher->hashPassword(new User(), 'secret'));
        $em->persist($otherProject);
        $em->persist($member);
        $em->flush();

        $ownerAssigned = new MemberProjectAlertEvent()
            ->setUser($owner)
            ->setProject($project)
            ->setEvent(MemberAlertEvent::IssueAssigned)
            ->setEnabled(false)
            ->setScope(MemberAlertScope::Involved);
        $ownerCommented = new MemberProjectAlertEvent()
            ->setUser($owner)
            ->setProject($otherProject)
            ->setEvent(MemberAlertEvent::IssueCommented);
        $memberResolved = new MemberProjectAlertEvent()
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

    public function testAccountAlertDestinationAndAttemptRepositories(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('account-alerts-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $accountEvents = self::getContainer()->get(MemberAccountAlertEventRepository::class);
        $destinations = self::getContainer()->get(NotificationDestinationRepository::class);
        $attempts = self::getContainer()->get(NotificationDeliveryAttemptRepository::class);
        $projectPreferences = self::getContainer()->get(MemberProjectAlertPreferenceRepository::class);

        $member = new User()
            ->setEmail('account-alerts-member@example.com')
            ->setDisplayName('Account Alerts Member')
            ->setPassword($hasher->hashPassword(new User(), 'secret'));
        $otherProject = new Project()
            ->setName('Destination Other')
            ->setSlug('destination-other');
        $em->persist($member);
        $em->persist($otherProject);
        $em->flush();

        $ownerProjectPreference = (new MemberProjectAlertPreference())
            ->setUser($owner)
            ->setProject($project)
            ->setEnabled(false);
        $memberProjectPreference = (new MemberProjectAlertPreference())
            ->setUser($member)
            ->setProject($project);
        $em->persist($ownerProjectPreference);
        $em->persist($memberProjectPreference);

        $ownerAssigned = (new MemberAccountAlertEvent())
            ->setUser($owner)
            ->setEvent(MemberAlertEvent::IssueAssigned)
            ->setEnabled(false);
        $ownerCommented = (new MemberAccountAlertEvent())
            ->setUser($owner)
            ->setEvent(MemberAlertEvent::IssueCommented);
        $memberResolved = (new MemberAccountAlertEvent())
            ->setUser($member)
            ->setEvent(MemberAlertEvent::IssueResolved);
        $em->persist($ownerAssigned);
        $em->persist($ownerCommented);
        $em->persist($memberResolved);

        $failedA = (new NotificationDestination())
            ->setProject($project)
            ->setLabel('Zulu webhook')
            ->setType(NotificationDestinationType::Http)
            ->setEndpointUrl('https://example.test/zulu')
            ->setEnabled(true)
            ->recordDeliveryFailure('boom', new DateTimeImmutable('2026-08-16 10:00:00'));
        $failedB = (new NotificationDestination())
            ->setProject($project)
            ->setLabel('Alpha webhook')
            ->setType(NotificationDestinationType::Http)
            ->setEndpointUrl('https://example.test/alpha')
            ->setEnabled(false)
            ->recordDeliveryFailure('denied', new DateTimeImmutable('2026-08-16 09:00:00'));
        $healthy = (new NotificationDestination())
            ->setProject($otherProject)
            ->setLabel('Healthy webhook')
            ->setType(NotificationDestinationType::Http)
            ->setEndpointUrl('https://example.test/healthy')
            ->setEnabled(true)
            ->recordDeliverySuccess(new DateTimeImmutable('2026-08-16 08:00:00'));
        $project->addNotificationDestination($failedA);
        $project->addNotificationDestination($failedB);
        $otherProject->addNotificationDestination($healthy);
        $em->persist($failedA);
        $em->persist($failedB);
        $em->persist($healthy);
        $em->flush();

        $attempt1 = $attempts->record($failedA, false, '500', new DateTimeImmutable('2026-08-16 10:00:00'));
        $attempt2 = $attempts->record($failedA, true, null, new DateTimeImmutable('2026-08-16 10:05:00'));
        $attempt3 = $attempts->record($failedB, false, '401', new DateTimeImmutable('2026-08-16 09:00:00'));
        $em->flush();

        $indexed = $accountEvents->findIndexedByEventForUser($owner);
        self::assertArrayHasKey(MemberAlertEvent::IssueAssigned->value, $indexed);
        self::assertFalse($indexed[MemberAlertEvent::IssueAssigned->value]->isEnabled());
        self::assertSame([], $accountEvents->findIndexedByUserIds([]));
        $byUser = $accountEvents->findIndexedByUserIds([(int) $owner->getId(), (int) $member->getId(), (int) $member->getId()]);
        self::assertArrayHasKey((int) $owner->getId(), $byUser);
        self::assertArrayHasKey(MemberAlertEvent::IssueResolved->value, $byUser[(int) $member->getId()]);
        self::assertSame($ownerAssigned->getId(), $accountEvents->findOneByUserAndEvent($owner, MemberAlertEvent::IssueAssigned)?->getId());

        self::assertSame([], $projectPreferences->findIndexedByProjectIdForUser($owner, []));
        self::assertSame($ownerProjectPreference->getId(), $projectPreferences->findOneByUserAndProject($owner, $project)?->getId());
        $projectPrefsByProject = $projectPreferences->findIndexedByProjectIdForUser($owner, [$project, $otherProject]);
        self::assertArrayHasKey((int) $project->getId(), $projectPrefsByProject);
        self::assertArrayNotHasKey((int) $otherProject->getId(), $projectPrefsByProject);
        self::assertSame([], $projectPreferences->findIndexedByUserIdsForProject($project, []));
        $projectPrefsByUser = $projectPreferences->findIndexedByUserIdsForProject($project, [(int) $owner->getId(), (int) $member->getId(), (int) $member->getId()]);
        self::assertArrayHasKey((int) $owner->getId(), $projectPrefsByUser);
        self::assertArrayHasKey((int) $member->getId(), $projectPrefsByUser);

        self::assertSame([$failedB->getId(), $failedA->getId()], array_map(static fn (NotificationDestination $d): ?int => $d->getId(), $destinations->findByProject($project)));
        self::assertSame([$failedA->getId()], array_map(static fn (NotificationDestination $d): ?int => $d->getId(), $destinations->findEnabledByProject($project)));
        self::assertSame([$failedA->getId()], array_map(static fn (NotificationDestination $d): ?int => $d->getId(), $destinations->findWithFailedLastDelivery($project, 1)));
        self::assertSame(2, $destinations->countWithFailedLastDeliveryInProjects([$project, $otherProject]));
        self::assertSame(0, $destinations->countWithFailedLastDeliveryInProjects([]));
        self::assertSame(2, $destinations->countWithFailedLastDelivery());
        self::assertCount(0, $destinations->findWithFailedLastDeliveryInProjects([], 10, 0));
        self::assertSame(
            [$failedB->getId()],
            array_map(
                static fn (NotificationDestination $d): ?int => $d->getId(),
                $destinations->findWithFailedLastDeliveryInProjects([$project, $otherProject], 1, 1),
            ),
        );

        self::assertSame([$attempt2->getId()], array_map(static fn ($row): ?int => $row->getId(), $attempts->findRecentForDestination($failedA, 1)));
        self::assertSame(
            [(int) $failedA->getId() => [$attempt2], (int) $failedB->getId() => [$attempt3]],
            $attempts->findRecentByDestinations([$failedA, $failedB], 1),
        );
        self::assertSame([], $attempts->findRecentByDestinations([new NotificationDestination()], 1));
        self::assertSame(1, $attempts->trimOlderThanKeep($failedA, 1));
        self::assertSame(0, $attempts->trimOlderThanKeep(new NotificationDestination(), 1));
        $attempts->removeAll([$attempt2, $attempt3]);
        $accountEvents->deleteAllForUser($owner);
        $em->flush();
        $em->clear();

        self::assertNull($accountEvents->findOneByUserAndEvent($owner, MemberAlertEvent::IssueAssigned));
        self::assertCount(0, $attempts->findRecentForDestination($failedA));
        self::assertCount(0, $attempts->findRecentForDestination($failedB));
    }

}
