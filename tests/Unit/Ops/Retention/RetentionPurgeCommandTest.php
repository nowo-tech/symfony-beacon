<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops\Retention;

use App\Ingest\Service\EventQuotaUsageStore;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueMergeService;
use App\Ops\Retention\RetentionPurgeCommand;
use App\Ops\Retention\RetentionPurger;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Tester\CommandTester;

final class RetentionPurgeCommandTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testDryRunDoesNotPurge(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setRetentionDays(14);
            $settings->setRetentionMaxEvents(1000);
        });
        $tester = new CommandTester(new RetentionPurgeCommand($this->purger($ops, []), $ops));
        self::assertSame(0, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('Dry-run only', $tester->getDisplay());
    }

    public function testWarnsWhenRetentionDisabled(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setRetentionDays(0);
            $settings->setRetentionMaxEvents(0);
        });
        $tester = new CommandTester(new RetentionPurgeCommand($this->purger($ops, []), $ops));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('nothing to do', $tester->getDisplay());
    }

    public function testReportsPurgeTotals(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setRetentionDays(7);
            $settings->setRetentionMaxEvents(0);
        });
        $project = new Project()->setName('P')->setSlug('p');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 3);
        $tester = new CommandTester(new RetentionPurgeCommand($this->purger($ops, [$project], deleteEvents: 4), $ops));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Purged across 1 project(s)', $tester->getDisplay());
        self::assertStringContainsString('4 events', $tester->getDisplay());
    }

    /**
     * @param list<Project> $projects
     */
    private function purger(object $ops, array $projects, int $deleteEvents = 0): RetentionPurger
    {
        $selectCalls = 0;
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static fn (string $sql): int => str_contains($sql, 'DELETE FROM event') ? $deleteEvents : 1,
        );
        $connection->method('fetchOne')->willReturn(0);
        $connection->method('fetchFirstColumn')->willReturnCallback(static function () use (&$selectCalls, $deleteEvents): array {
            if ($deleteEvents < 1 || $selectCalls++ > 0) {
                return [];
            }

            return range(1, $deleteEvents);
        });

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('contains')->willReturn(true);
        $em->method('clear');

        $projectsRepo = $this->createStub(ProjectRepository::class);
        $projectsRepo->method('findAll')->willReturn($projects);
        $projectsRepo->method('find')->willReturnCallback(
            static fn (int $id): ?Project => $projects[0] ?? null,
        );
        $events = $this->createStub(EventRepository::class);

        return new RetentionPurger(
            $em,
            $projectsRepo,
            new IssueMergeService(
                $events,
                $this->createStub(IssueRepository::class),
                new IssueHistoryRecorder($em),
                $em,
            ),
            new ProjectGovernanceResolver($ops, new EventQuotaUsageStore($events, new ArrayAdapter())),
        );
    }
}
