<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueDuplicateMarker;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueMergeService;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Project\Entity\Project;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class IssueDuplicateMarkerTest extends TestCase
{
    private IssueRepository&Stub $issueRepository;
    private EntityManagerInterface&Stub $entityManager;
    /** @var list<object> */
    private array $persisted = [];
    private int $flushCount = 0;
    private IssueDuplicateMarker $marker;

    protected function setUp(): void
    {
        $this->persisted = [];
        $this->flushCount = 0;
        $this->issueRepository = $this->createStub(IssueRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $this->entityManager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushCount;
        });

        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('findBy')->willReturn([]);

        $merge = new IssueMergeService(
            $eventRepository,
            $this->issueRepository,
            new IssueHistoryRecorder($this->entityManager),
            $this->entityManager,
        );

        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settingsRepo);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/i');
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findEnabledByProject')->willReturn([]);

        $this->marker = new IssueDuplicateMarker(
            $this->issueRepository,
            $merge,
            new IssueHistoryRecorder($this->entityManager),
            new UserActionRecorder($this->entityManager, new RequestStack()),
            new NotificationDispatcher(
                $destinations,
                $this->createStub(NotificationDigestBufferRepository::class),
                new NotificationPayloadBuilder($urls),
                new QuietHoursEvaluator(),
                new NotificationCircuitBreaker($ops),
                $this->createStub(MessageBusInterface::class),
                $this->entityManager,
                $this->createStub(MemberIssueRealtimeNotifierInterface::class),
            ),
            $this->entityManager,
        );
    }

    public function testRejectsEmptySelfAndMissingCanonical(): void
    {
        $project = $this->project(1);
        $issue = $this->issue(10, $project, 'Source');

        self::assertSame('issues.duplicate_invalid', $this->marker->mark($project, $issue, new User(), '  ', false)['flash']);
        self::assertSame('issues.duplicate_self', $this->marker->mark($project, $issue, new User(), $issue->getUuid(), false)['flash']);

        $this->issueRepository->method('findOneByProjectAndUuid')->willReturn(null);
        self::assertSame('issues.duplicate_not_found', $this->marker->mark($project, $issue, new User(), 'missing-uuid', false)['flash']);
    }

    public function testRejectsWrongProjectAndCircular(): void
    {
        $project = $this->project(1);
        $other = $this->project(2);
        $issue = $this->issue(10, $project, 'Source');
        $foreign = $this->issue(11, $other, 'Foreign');
        $this->issueRepository->method('findOneByProjectAndUuid')->willReturn($foreign);
        self::assertSame('issues.duplicate_not_found', $this->marker->mark($project, $issue, new User(), $foreign->getUuid(), false)['flash']);

        $mid = $this->issue(12, $project, 'Mid');
        $canonical = $this->issue(13, $project, 'Canonical');
        $mid->setDuplicateOf($issue);
        $canonical->setDuplicateOf($mid);
        $this->issueRepository = $this->createStub(IssueRepository::class);
        $this->issueRepository->method('findOneByProjectAndUuid')->willReturn($canonical);
        $this->rebuildMarker();
        self::assertSame('issues.duplicate_circular', $this->marker->mark($project, $issue, new User(), $canonical->getUuid(), false)['flash']);
    }

    public function testMarksDuplicateWithoutMergingEvents(): void
    {
        $project = $this->project(1)->setName('Demo');
        $issue = $this->issue(10, $project, 'Source');
        $canonical = $this->issue(11, $project, 'Canonical');
        $actor = $this->user(5);
        $this->issueRepository->method('findOneByProjectAndUuid')->willReturn($canonical);

        $result = $this->marker->mark($project, $issue, $actor, $canonical->getUuid(), false);

        self::assertTrue($result['ok']);
        self::assertSame('issues.duplicate_saved', $result['flash']);
        self::assertNull($result['redirect_issue_uuid']);
        self::assertSame($canonical, $issue->getDuplicateOf());
        self::assertSame(IssueStatus::Ignored, $issue->getStatus());
        self::assertGreaterThanOrEqual(1, $this->flushCount);
        self::assertTrue(array_any(
            $this->persisted,
            static fn (object $e): bool => $e instanceof \App\Identity\Entity\UserAction
                && UserActionType::IssueMarkedDuplicate === $e->getAction(),
        ));
    }

    public function testMergeEventsRedirectsToCanonical(): void
    {
        $project = $this->project(1)->setName('Demo');
        $issue = $this->issue(10, $project, 'Source');
        $canonical = $this->issue(11, $project, 'Canonical');
        $actor = $this->user(5);
        $this->issueRepository->method('findOneByProjectAndUuid')->willReturn($canonical);

        $result = $this->marker->mark($project, $issue, $actor, $canonical->getUuid(), true);

        self::assertTrue($result['ok']);
        self::assertSame('issues.merge_saved', $result['flash']);
        self::assertSame($canonical->getUuid(), $result['redirect_issue_uuid']);
        self::assertSame($canonical, $issue->getDuplicateOf());
    }

    private function rebuildMarker(): void
    {
        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('findBy')->willReturn([]);
        $merge = new IssueMergeService(
            $eventRepository,
            $this->issueRepository,
            new IssueHistoryRecorder($this->entityManager),
            $this->entityManager,
        );
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settingsRepo);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/i');
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findEnabledByProject')->willReturn([]);
        $this->marker = new IssueDuplicateMarker(
            $this->issueRepository,
            $merge,
            new IssueHistoryRecorder($this->entityManager),
            new UserActionRecorder($this->entityManager, new RequestStack()),
            new NotificationDispatcher(
                $destinations,
                $this->createStub(NotificationDigestBufferRepository::class),
                new NotificationPayloadBuilder($urls),
                new QuietHoursEvaluator(),
                new NotificationCircuitBreaker($ops),
                $this->createStub(MessageBusInterface::class),
                $this->entityManager,
                $this->createStub(MemberIssueRealtimeNotifierInterface::class),
            ),
            $this->entityManager,
        );
    }

    private function project(int $id): Project
    {
        $project = (new Project())->setSlug('p'.$id)->setName('P'.$id);
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, $id);

        return $project;
    }

    private function issue(int $id, Project $project, string $title): Issue
    {
        $issue = (new Issue())->setProject($project)->setTitle($title)->setStatus(IssueStatus::Unresolved);
        (new ReflectionProperty(Issue::class, 'id'))->setValue($issue, $id);

        return $issue;
    }

    private function user(int $id): User
    {
        $user = (new User())->setEmail('u'.$id.'@example.com');
        (new ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
