<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Identity\Entity\User;
use App\Notifications\Entity\MemberAccountAlertEvent;
use App\Notifications\Entity\MemberProjectAlertEvent;
use App\Notifications\Entity\MemberProjectAlertPreference;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Enum\MemberAlertScope;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Notifications\Service\MemberAlertPreferenceManager;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class MemberAlertPreferenceManagerTest extends TestCase
{
    private MemberProjectAlertPreferenceRepository&Stub $projectPrefRepo;
    private MemberAccountAlertEventRepository&Stub $accountEventRepo;
    private MemberProjectAlertEventRepository&Stub $projectEventRepo;

    protected function setUp(): void
    {
        $this->projectPrefRepo = $this->createStub(MemberProjectAlertPreferenceRepository::class);
        $this->accountEventRepo = $this->createStub(MemberAccountAlertEventRepository::class);
        $this->projectEventRepo = $this->createStub(MemberProjectAlertEventRepository::class);
    }

    public function testSaveAccountEventsRemovesDefaultRowsAndPersistsOverrides(): void
    {
        $user = $this->user();
        $existingDefault = new MemberAccountAlertEvent();
        $existingDefault->setUser($user);
        $existingDefault->setEvent(MemberAlertEvent::IssueNew);
        $existingDefault->setEnabled(true);
        $existingDefault->setScope(MemberAlertScope::All);

        $existingOverride = new MemberAccountAlertEvent();
        $existingOverride->setUser($user);
        $existingOverride->setEvent(MemberAlertEvent::IssueAssigned);
        $existingOverride->setEnabled(false);
        $existingOverride->setScope(MemberAlertScope::All);

        $this->accountEventRepo->method('findIndexedByEventForUser')->willReturn([
            MemberAlertEvent::IssueNew->value => $existingDefault,
            MemberAlertEvent::IssueAssigned->value => $existingOverride,
        ]);

        $removed = [];
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('remove')->willReturnCallback(
            static function (object $entity) use (&$removed): void {
                $removed[] = $entity;
            },
        );
        $em->expects(self::atLeastOnce())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            },
        );
        $manager = new MemberAlertPreferenceManager(
            $this->projectPrefRepo,
            $this->accountEventRepo,
            $this->projectEventRepo,
            $em,
        );

        $raw = [];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $raw[$event->value] = ['enabled' => true, 'scope' => MemberAlertScope::All->value];
        }
        $raw[MemberAlertEvent::IssueAssigned->value] = ['enabled' => false, 'scope' => MemberAlertScope::All->value];
        $raw[MemberAlertEvent::IssueCommented->value] = [
            'enabled' => true,
            'scope' => MemberAlertScope::Involved->value,
        ];

        $manager->saveAccountEvents($user, false, $raw);

        self::assertFalse($user->isMemberAlertsEnabled());
        self::assertContains($existingDefault, $removed);
        self::assertNotContains($existingOverride, $removed);
        self::assertFalse($existingOverride->isEnabled());
        self::assertTrue(array_any(
            $persisted,
            static fn (object $e): bool => $e instanceof MemberAccountAlertEvent
                && MemberAlertEvent::IssueCommented === $e->getEvent()
                && MemberAlertScope::Involved === $e->getScope(),
        ));
    }

    public function testSaveProjectPreferencesResetClearsOverridesAndRemovesDefaultPref(): void
    {
        $user = $this->user();
        $project = $this->project(1);
        $pref = new MemberProjectAlertPreference();
        $pref->setUser($user);
        $pref->setProject($project);
        $pref->setEnabled(true);

        $projectEventRepo = $this->createMock(MemberProjectAlertEventRepository::class);
        $projectEventRepo->expects(self::once())->method('deleteAllForUserAndProject')
            ->with($user, $project);
        $this->projectPrefRepo->method('findOneByUserAndProject')->willReturn($pref);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($pref);

        $manager = new MemberAlertPreferenceManager(
            $this->projectPrefRepo,
            $this->accountEventRepo,
            $projectEventRepo,
            $em,
        );

        $manager->saveProjectPreferences($user, [[
            'project' => $project,
            'enabled' => true,
            'resetOverrides' => true,
            'events' => [],
        ]]);
    }

    public function testSaveProjectPreferencesPersistsDisabledAndEventOverrides(): void
    {
        $user = $this->user();
        $project = $this->project(2);

        $this->accountEventRepo->method('findIndexedByEventForUser')->willReturn([]);
        $this->projectEventRepo->method('findIndexedByEventForUserAndProject')->willReturn([]);
        $this->projectPrefRepo->method('findOneByUserAndProject')->willReturn(null);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            },
        );
        $manager = new MemberAlertPreferenceManager(
            $this->projectPrefRepo,
            $this->accountEventRepo,
            $this->projectEventRepo,
            $em,
        );

        $events = [];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $events[$event->value] = ['enabled' => true, 'scope' => MemberAlertScope::All->value];
        }
        $events[MemberAlertEvent::IssueNew->value] = [
            'enabled' => false,
            'scope' => MemberAlertScope::All->value,
        ];

        $manager->saveProjectPreferences($user, [[
            'project' => $project,
            'enabled' => false,
            'resetOverrides' => false,
            'events' => $events,
        ]]);

        self::assertTrue(array_any(
            $persisted,
            static fn (object $e): bool => $e instanceof MemberProjectAlertEvent
                && MemberAlertEvent::IssueNew === $e->getEvent()
                && !$e->isEnabled(),
        ));
        self::assertTrue(array_any(
            $persisted,
            static fn (object $e): bool => $e instanceof MemberProjectAlertPreference && !$e->isEnabled(),
        ));
    }

    public function testSaveProjectPreferencesRemovesOverrideWhenMatchingAccount(): void
    {
        $user = $this->user();
        $project = $this->project(3);
        $account = new MemberAccountAlertEvent();
        $account->setEvent(MemberAlertEvent::IssueNew);
        $account->setEnabled(false);
        $account->setScope(MemberAlertScope::All);
        $override = new MemberProjectAlertEvent();
        $override->setUser($user);
        $override->setProject($project);
        $override->setEvent(MemberAlertEvent::IssueNew);
        $override->setEnabled(false);
        $override->setScope(MemberAlertScope::All);

        $this->accountEventRepo->method('findIndexedByEventForUser')->willReturn([
            MemberAlertEvent::IssueNew->value => $account,
        ]);
        $this->projectEventRepo->method('findIndexedByEventForUserAndProject')->willReturn([
            MemberAlertEvent::IssueNew->value => $override,
        ]);
        $this->projectPrefRepo->method('findOneByUserAndProject')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($override);
        $manager = new MemberAlertPreferenceManager(
            $this->projectPrefRepo,
            $this->accountEventRepo,
            $this->projectEventRepo,
            $em,
        );

        $events = [];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $events[$event->value] = [
                'enabled' => MemberAlertEvent::IssueNew !== $event,
                'scope' => MemberAlertScope::All->value,
            ];
        }
        // Match account for IssueNew (disabled) so override row is removed.
        $events[MemberAlertEvent::IssueNew->value] = ['enabled' => false, 'scope' => MemberAlertScope::All->value];

        $manager->saveProjectPreferences($user, [[
            'project' => $project,
            'enabled' => true,
            'events' => $events,
        ]]);
    }

    public function testUiLoadersMergeAccountAndProjectOverrides(): void
    {
        $user = $this->user();
        $project = $this->project(9);
        $pref = new MemberProjectAlertPreference();
        $pref->setEnabled(false);
        $account = new MemberAccountAlertEvent();
        $account->setEvent(MemberAlertEvent::IssueAssigned);
        $account->setEnabled(false);
        $account->setScope(MemberAlertScope::Involved);
        $override = new MemberProjectAlertEvent();
        $override->setEvent(MemberAlertEvent::IssueAssigned);
        $override->setEnabled(true);
        $override->setScope(MemberAlertScope::All);

        $this->projectPrefRepo->method('findIndexedByProjectIdForUser')->willReturn([9 => $pref]);
        $this->accountEventRepo->method('findIndexedByEventForUser')->willReturn([
            MemberAlertEvent::IssueAssigned->value => $account,
        ]);
        $this->projectEventRepo->method('findIndexedByEventForUserAndProject')->willReturn([
            MemberAlertEvent::IssueAssigned->value => $override,
        ]);

        $manager = new MemberAlertPreferenceManager(
            $this->projectPrefRepo,
            $this->accountEventRepo,
            $this->projectEventRepo,
            $this->createStub(EntityManagerInterface::class),
        );

        $rows = $manager->projectRowsForUi($user, [$project, $this->project(null)]);
        self::assertCount(2, $rows);
        self::assertFalse($rows[0]['enabled']);
        self::assertTrue($rows[0]['hasOverrides']);
        self::assertTrue($rows[0]['events'][MemberAlertEvent::IssueAssigned->value]['enabled']);
        self::assertSame(MemberAlertScope::All->value, $rows[0]['events'][MemberAlertEvent::IssueAssigned->value]['scope']);
        self::assertTrue($rows[1]['enabled']);

        $accountUi = $manager->accountEventsForUi($user);
        self::assertFalse($accountUi[MemberAlertEvent::IssueAssigned->value]['enabled']);
        self::assertSame(MemberAlertScope::Involved->value, $accountUi[MemberAlertEvent::IssueAssigned->value]['scope']);
        self::assertTrue($accountUi[MemberAlertEvent::IssueNew->value]['enabled']);
    }

    private function user(): User
    {
        $user = new User();
        $user->setEmail(uniqid('mgr-', true).'@example.com');
        $user->setPassword('x');

        return $user;
    }

    private function project(?int $id): Project
    {
        $project = new Project();
        $project->setName('P');
        $project->setSlug('p-'.($id ?? 'x'));
        if (null !== $id) {
            new ReflectionProperty(Project::class, 'id')->setValue($project, $id);
        }

        return $project;
    }
}
