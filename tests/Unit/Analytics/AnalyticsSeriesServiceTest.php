<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Analytics\Service\AnalyticsSeriesService;
use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class AnalyticsSeriesServiceTest extends TestCase
{
    public function testBuildZeroFillsMissingDaysFromDailyStats(): void
    {
        $project = $this->createStub(Project::class);
        $tz = new DateTimeZone('UTC');
        $from = new DateTimeImmutable('2026-01-01', $tz);
        $to = new DateTimeImmutable('2026-01-03', $tz);

        $mid = new DailyProjectStat();
        $mid->setStatDate(new DateTimeImmutable('2026-01-02', $tz));
        $mid->incrementErrorCount(4);
        $mid->incrementTransactionCount(2);
        $mid->incrementNPlusOneCount(1);

        $stats = $this->createMock(DailyProjectStatRepository::class);
        $stats->expects(self::once())
            ->method('findInRange')
            ->with($project, $from, $to)
            ->willReturn([$mid]);

        $events = $this->createMock(EventRepository::class);
        $events->expects(self::never())->method('countErrorsByDay');

        $points = new AnalyticsSeriesService($stats, $events)->build(
            $project,
            $from,
            $to,
            null,
            null,
            null,
        );

        self::assertCount(3, $points);
        self::assertSame(0, $points[0]->errorCount);
        self::assertSame(4, $points[1]->errorCount);
        self::assertSame(2, $points[1]->transactionCount);
        self::assertSame(1, $points[1]->nPlusOneCount);
        self::assertSame(0, $points[2]->errorCount);
    }

    public function testBuildWithEnvironmentFilterUsesEventCountsAndNullsOtherSeries(): void
    {
        $project = $this->createStub(Project::class);
        $tz = new DateTimeZone('UTC');
        $from = new DateTimeImmutable('2026-02-01', $tz);
        $to = new DateTimeImmutable('2026-02-02', $tz);

        $stats = $this->createMock(DailyProjectStatRepository::class);
        $stats->expects(self::never())->method('findInRange');

        $events = $this->createMock(EventRepository::class);
        $events->expects(self::once())
            ->method('countErrorsByDay')
            ->with($project, $from, $to, 'prod', null, null)
            ->willReturn(['2026-02-01' => 3]);

        $points = new AnalyticsSeriesService($stats, $events)->build(
            $project,
            $from,
            $to,
            'prod',
            null,
            null,
        );

        self::assertCount(2, $points);
        self::assertSame(3, $points[0]->errorCount);
        self::assertNull($points[0]->transactionCount);
        self::assertNull($points[0]->nPlusOneCount);
        self::assertSame(0, $points[1]->errorCount);
    }

    public function testBuildWithReleaseAndLevelFiltersPassesThroughToEvents(): void
    {
        $project = $this->createStub(Project::class);
        $tz = new DateTimeZone('UTC');
        $from = new DateTimeImmutable('2026-03-10', $tz);
        $to = new DateTimeImmutable('2026-03-10', $tz);

        $stats = $this->createMock(DailyProjectStatRepository::class);
        $stats->expects(self::never())->method('findInRange');

        $events = $this->createMock(EventRepository::class);
        $events->expects(self::once())
            ->method('countErrorsByDay')
            ->with($project, $from, $to, null, '2.0.0', 'warning')
            ->willReturn(['2026-03-10' => 7]);

        $points = new AnalyticsSeriesService($stats, $events)->build(
            $project,
            $from,
            $to,
            null,
            '2.0.0',
            'warning',
        );

        self::assertCount(1, $points);
        self::assertSame(7, $points[0]->errorCount);
        self::assertNull($points[0]->transactionCount);
        self::assertSame(
            ['date' => '2026-03-10', 'errors' => 7, 'transactions' => null, 'nplus1' => null],
            $points[0]->toChartArray(),
        );
    }

    public function testHasFilters(): void
    {
        $svc = new AnalyticsSeriesService(
            $this->createStub(DailyProjectStatRepository::class),
            $this->createStub(EventRepository::class),
        );

        self::assertFalse($svc->hasFilters(null, null, null));
        self::assertFalse($svc->hasFilters('', '', ''));
        self::assertTrue($svc->hasFilters('prod', null, null));
        self::assertTrue($svc->hasFilters(null, '1.0', null));
        self::assertTrue($svc->hasFilters(null, null, 'error'));
    }
}
