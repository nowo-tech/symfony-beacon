<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops\Retention;

use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueMergeService;
use App\Ops\Retention\RetentionPurger;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class RetentionPurgerTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    private EntityManagerInterface&Stub $entityManager;
    private ProjectRepository&Stub $projectRepository;
    private Connection&Stub $connection;
    /** @var list<string> */
    private array $sql = [];
    private RetentionPurger $purger;

    protected function setUp(): void
    {
        $this->sql = [];
        $this->connection = $this->createStub(Connection::class);
        $this->connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params = []): int {
            $this->sql[] = $sql;

            return str_contains($sql, 'DELETE FROM event') ? 3 : 1;
        });
        $this->connection->method('fetchOne')->willReturn(0);
        $this->connection->method('fetchFirstColumn')->willReturn([]);

        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->entityManager->method('contains')->willReturn(true);
        $this->entityManager->method('clear');

        $this->projectRepository = $this->createStub(ProjectRepository::class);
        $events = $this->createStub(EventRepository::class);
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setRetentionDays(30);
            $settings->setRetentionMaxEvents(0);
        });

        $this->purger = new RetentionPurger(
            $this->entityManager,
            $this->projectRepository,
            new IssueMergeService(
                $events,
                $this->createStub(IssueRepository::class),
                new IssueHistoryRecorder($this->entityManager),
                $this->entityManager,
            ),
            new ProjectGovernanceResolver($events, $ops),
        );
    }

    public function testPurgeProjectWithoutIdReturnsZeros(): void
    {
        self::assertSame(
            ['events' => 0, 'issues' => 0, 'transactions' => 0, 'stats' => 0],
            $this->purger->purgeProject(new Project()),
        );
    }

    public function testPurgeSkipsProjectsWithoutRetention(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setRetentionDays(0);
            $settings->setRetentionMaxEvents(0);
        });
        $events = $this->createStub(EventRepository::class);
        $this->projectRepository->method('findAll')->willReturn([$this->project(1)]);
        $this->purger = new RetentionPurger(
            $this->entityManager,
            $this->projectRepository,
            new IssueMergeService(
                $events,
                $this->createStub(IssueRepository::class),
                new IssueHistoryRecorder($this->entityManager),
                $this->entityManager,
            ),
            new ProjectGovernanceResolver($events, $ops),
        );

        $totals = $this->purger->purge(new DateTimeImmutable('2026-08-13T00:00:00+00:00'));
        self::assertSame(0, $totals['projects']);
        self::assertSame([], $this->sql);
    }

    public function testPurgeRunsAgeDeletesAndClears(): void
    {
        $project = $this->project(7);
        $this->projectRepository->method('findAll')->willReturn([$project]);
        $this->projectRepository->method('find')->willReturn($project);

        $totals = $this->purger->purge(new DateTimeImmutable('2026-08-13T12:00:00+00:00'));

        self::assertSame(1, $totals['projects']);
        self::assertSame(3, $totals['events']);
        self::assertGreaterThan(0, $totals['issues']);
        self::assertNotSame([], $this->sql);
        self::assertTrue(array_any($this->sql, static fn (string $s): bool => str_contains($s, 'DELETE FROM event')));
    }

    public function testMaxEventsCapDeletesOldest(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setRetentionDays(0);
            $settings->setRetentionMaxEvents(2);
        });
        $events = $this->createStub(EventRepository::class);
        $project = $this->project(3);
        $this->projectRepository->method('find')->willReturn($project);
        $this->connection = $this->createStub(Connection::class);
        $this->connection->method('fetchOne')->willReturn(5);
        $this->connection->method('fetchFirstColumn')->willReturn([10, 11, 12]);
        $this->connection->method('executeStatement')->willReturnCallback(function (string $sql): int {
            $this->sql[] = $sql;

            return 3;
        });
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->entityManager->method('clear');

        $this->purger = new RetentionPurger(
            $this->entityManager,
            $this->projectRepository,
            new IssueMergeService(
                $events,
                $this->createStub(IssueRepository::class),
                new IssueHistoryRecorder($this->entityManager),
                $this->entityManager,
            ),
            new ProjectGovernanceResolver($events, $ops),
        );

        $result = $this->purger->purgeProject($project);
        self::assertSame(3, $result['events']);
        self::assertTrue(array_any($this->sql, static fn (string $s): bool => str_contains($s, 'DELETE FROM event WHERE id IN')));
    }

    public function testPurgeSkipsInvalidAndUnreloadableProjects(): void
    {
        $detached = $this->project(9);
        $events = $this->createStub(EventRepository::class);
        $this->projectRepository = $this->createStub(ProjectRepository::class);
        $this->projectRepository->method('findAll')->willReturn([new Project(), 'skip', $detached]);
        $this->projectRepository->method('find')->willReturn(null);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->entityManager->method('contains')->willReturnCallback(static fn (mixed $value): bool => $value !== $detached);
        $this->entityManager->method('clear');
        $this->purger = new RetentionPurger(
            $this->entityManager,
            $this->projectRepository,
            new IssueMergeService(
                $events,
                $this->createStub(IssueRepository::class),
                new IssueHistoryRecorder($this->entityManager),
                $this->entityManager,
            ),
            new ProjectGovernanceResolver($events, $this->opsDefaultsWith(static function ($settings): void {
                $settings->setRetentionDays(30);
                $settings->setRetentionMaxEvents(0);
            })),
        );

        $totals = $this->purger->purge(new DateTimeImmutable('2026-08-13T00:00:00+00:00'));

        self::assertSame(0, $totals['projects']);
        self::assertSame([], $this->sql);
    }

    public function testPurgeReloadsDetachedProjectBeforePurging(): void
    {
        $detached = $this->project(11);
        $reloaded = $this->project(11);
        $this->projectRepository->method('findAll')->willReturn([$detached]);
        $this->projectRepository->method('find')->willReturn($reloaded);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->entityManager->method('contains')->willReturn(false);
        $this->entityManager->method('clear');

        $events = $this->createStub(EventRepository::class);
        $this->purger = new RetentionPurger(
            $this->entityManager,
            $this->projectRepository,
            new IssueMergeService(
                $events,
                $this->createStub(IssueRepository::class),
                new IssueHistoryRecorder($this->entityManager),
                $this->entityManager,
            ),
            new ProjectGovernanceResolver($events, $this->opsDefaultsWith(static function ($settings): void {
                $settings->setRetentionDays(30);
                $settings->setRetentionMaxEvents(0);
            })),
        );

        $totals = $this->purger->purge(new DateTimeImmutable('2026-08-13T00:00:00+00:00'));
        self::assertSame(1, $totals['projects']);
    }

    public function testPurgeSkipsDetachedProjectsWhoseIdDisappearsBeforeReload(): void
    {
        $detached = new class extends Project {
            private int $calls = 0;

            public function getId(): ?int
            {
                ++$this->calls;

                return 1 === $this->calls ? 12 : null;
            }
        };

        $this->projectRepository->method('findAll')->willReturn([$detached]);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->entityManager->method('contains')->willReturn(false);
        $this->entityManager->method('clear');

        $events = $this->createStub(EventRepository::class);
        $this->purger = new RetentionPurger(
            $this->entityManager,
            $this->projectRepository,
            new IssueMergeService(
                $events,
                $this->createStub(IssueRepository::class),
                new IssueHistoryRecorder($this->entityManager),
                $this->entityManager,
            ),
            new ProjectGovernanceResolver($events, $this->opsDefaultsWith(static function ($settings): void {
                $settings->setRetentionDays(30);
                $settings->setRetentionMaxEvents(0);
            })),
        );

        self::assertSame(0, $this->purger->purge(new DateTimeImmutable('2026-08-13T00:00:00+00:00'))['projects']);
    }

    public function testMaxEventsCapSkipsDeleteWhenNoIdsNeedRemoval(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setRetentionDays(0);
            $settings->setRetentionMaxEvents(2);
        });
        $events = $this->createStub(EventRepository::class);
        $project = $this->project(4);
        $this->connection = $this->createStub(Connection::class);
        $this->connection->method('fetchOne')->willReturn(5);
        $this->connection->method('fetchFirstColumn')->willReturn([]);
        $this->connection->method('executeStatement')->willReturnCallback(function (string $sql): int {
            $this->sql[] = $sql;

            return 0;
        });
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->entityManager->method('clear');
        $this->projectRepository->method('find')->willReturn($project);

        $this->purger = new RetentionPurger(
            $this->entityManager,
            $this->projectRepository,
            new IssueMergeService(
                $events,
                $this->createStub(IssueRepository::class),
                new IssueHistoryRecorder($this->entityManager),
                $this->entityManager,
            ),
            new ProjectGovernanceResolver($events, $ops),
        );

        self::assertSame(
            ['events' => 0, 'issues' => 0, 'transactions' => 0, 'stats' => 0],
            $this->purger->purgeProject($project),
        );
        self::assertFalse(array_any($this->sql, static fn (string $sql): bool => str_contains($sql, 'DELETE FROM event WHERE id IN')));
    }

    private function project(int $id): Project
    {
        $project = new Project()->setName('P'.$id)->setSlug('p'.$id);
        new ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }
}
