<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops\Service;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Ops\Service\OpsOverviewService;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectOpsStatsService;
use App\Shared\Health\MessengerQueueHealth;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class OpsOverviewServiceTest extends TestCase
{
    public function testIsSpikeThreshold(): void
    {
        self::assertFalse(OpsOverviewService::isSpike(3, 0.0));
        self::assertTrue(OpsOverviewService::isSpike(4, 0.0));
        self::assertFalse(OpsOverviewService::isSpike(10, 5.0));
        self::assertTrue(OpsOverviewService::isSpike(11, 5.0));
    }

    public function testBuildAggregatesOpenSuspendedAndFailed(): void
    {
        $active = $this->project(1, 'Active', ingestEnabled: true);
        $suspended = $this->project(2, 'Paused', ingestEnabled: false);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAllOrdered')->willReturn([$active, $suspended]);

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('countByStatusForProjectIds')->willReturn([1 => 5, 2 => 0]);

        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedSinceForProjectIds')->willReturn([1 => 1, 2 => 0]);
        $events->method('findLastReceivedAtForProjectIds')->willReturn([]);

        $today = new DateTimeImmutable('now', new DateTimeZone('UTC'))->setTime(0, 0);
        $stat = new DailyProjectStat();
        $stat->setProject($active);
        $stat->setStatDate($today);
        $stat->incrementErrorCount(20);

        $daily = $this->createStub(DailyProjectStatRepository::class);
        $daily->method('findLastDaysForProjects')->willReturn([1 => [$stat], 2 => []]);

        $destination = new NotificationDestination();
        $destination->setProject($active);
        $destination->setLabel('Hooks');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hook');
        $destination->recordDeliveryFailure('timeout');

        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findWithFailedLastDelivery')->willReturn([$destination]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(new RuntimeException('offline'));

        $overview = new OpsOverviewService(
            new MessengerQueueHealth($em),
            $projects,
            new ProjectOpsStatsService($issues, $events),
            $daily,
            $destinations,
        )->build();

        self::assertSame(['pending' => null, 'available' => false], $overview['messenger']);
        self::assertSame(5, $overview['open_issues_total']);
        self::assertCount(1, $overview['open_issues_by_project']);
        self::assertSame($active, $overview['open_issues_by_project'][0]['project']);
        self::assertSame(1, $overview['suspended_count']);
        self::assertSame([$suspended], $overview['suspended_projects']);
        self::assertCount(1, $overview['spikes']);
        self::assertSame(20, $overview['spikes'][0]['errors_last_1d']);
        self::assertCount(1, $overview['failed_deliveries']);
        self::assertSame('Hooks', $overview['failed_deliveries'][0]['label']);
        self::assertNull($overview['filter_project']);
        self::assertSame([$active, $suspended], $overview['projects']);
    }

    public function testBuildRespectsProjectFilter(): void
    {
        $only = $this->project(3, 'Solo', ingestEnabled: true);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAllOrdered')->willReturn([$only, $this->project(4, 'Other', true)]);

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('countByStatusForProjectIds')->willReturn([3 => 2]);

        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedSinceForProjectIds')->willReturn([3 => 0]);
        $events->method('findLastReceivedAtForProjectIds')->willReturn([]);

        $daily = $this->createStub(DailyProjectStatRepository::class);
        $daily->method('findLastDaysForProjects')->willReturn([3 => []]);

        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findWithFailedLastDelivery')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(new RuntimeException('offline'));

        $overview = new OpsOverviewService(
            new MessengerQueueHealth($em),
            $projects,
            new ProjectOpsStatsService($issues, $events),
            $daily,
            $destinations,
        )->build($only);

        self::assertSame(2, $overview['open_issues_total']);
        self::assertSame($only, $overview['filter_project']);
        self::assertCount(2, $overview['projects']);
    }

    public function testBuildSkipsUnsavedProjectsAndFailedDestinationsWithoutProject(): void
    {
        $saved = $this->project(5, 'Saved', ingestEnabled: true);
        $unsaved = new Project();
        $unsaved->setName('Unsaved');
        $unsaved->setSlug('unsaved');

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAllOrdered')->willReturn([$saved, $unsaved]);

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('countByStatusForProjectIds')->willReturn([5 => 3]);

        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedSinceForProjectIds')->willReturn([5 => 0]);
        $events->method('findLastReceivedAtForProjectIds')->willReturn([]);

        $daily = $this->createStub(DailyProjectStatRepository::class);
        $daily->method('findLastDaysForProjects')->willReturn([5 => []]);

        $orphan = new NotificationDestination();
        $orphan->setLabel('Orphan');
        $orphan->setType(NotificationDestinationType::Http);
        $orphan->setEndpointUrl('https://example.test/orphan');
        $orphan->recordDeliveryFailure('no project');

        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findWithFailedLastDelivery')->willReturn([$orphan]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(new RuntimeException('offline'));

        $overview = new OpsOverviewService(
            new MessengerQueueHealth($em),
            $projects,
            new ProjectOpsStatsService($issues, $events),
            $daily,
            $destinations,
        )->build();

        self::assertSame(3, $overview['open_issues_total']);
        self::assertCount(1, $overview['open_issues_by_project']);
        self::assertSame($saved, $overview['open_issues_by_project'][0]['project']);
        self::assertSame([], $overview['spikes']);
        self::assertSame([], $overview['failed_deliveries']);
    }

    private function project(int $id, string $name, bool $ingestEnabled): Project
    {
        $project = new Project();
        $project->setName($name);
        $project->setSlug(strtolower($name));
        $project->setIngestEnabled($ingestEnabled);

        $property = new ReflectionProperty(Project::class, 'id');
        $property->setValue($project, $id);

        return $project;
    }
}
