<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Project\Entity\Project;
use App\Project\Service\ProjectOpsStatsService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ProjectOpsStatsServiceTest extends TestCase
{
    public function testForProjectDelegatesToRepositories(): void
    {
        $project = $this->project(7);
        $last = new DateTimeImmutable('-1 hour');

        $issues = $this->createMock(IssueSearchRepository::class);
        $issues->expects(self::once())
            ->method('countByProjectAndStatus')
            ->with($project, IssueStatus::Unresolved)
            ->willReturn(4);

        $events = $this->createMock(EventRepository::class);
        $events->expects(self::once())
            ->method('countReceivedSinceForProject')
            ->with($project, self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn(12);
        $events->expects(self::once())
            ->method('findLastReceivedAtForProject')
            ->with($project)
            ->willReturn($last);

        $stats = new ProjectOpsStatsService($issues, $events)->forProject($project);

        self::assertSame(4, $stats['open_issues']);
        self::assertSame(12, $stats['events_last_7d']);
        self::assertSame($last, $stats['last_ingest_at']);
    }

    public function testForProjectsMapsByIdAndDefaultsMissing(): void
    {
        $a = $this->project(1);
        $b = $this->project(2);
        $orphan = new Project();
        $orphan->setName('No id');
        $orphan->setSlug('no-id');

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('countByStatusForProjectIds')->willReturn([1 => 3]);

        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedSinceForProjectIds')->willReturn([1 => 9, 2 => 1]);
        $events->method('findLastReceivedAtForProjectIds')->willReturn([2 => new DateTimeImmutable('2024-01-01')]);

        $map = new ProjectOpsStatsService($issues, $events)->forProjects([$a, $b, $orphan]);

        self::assertArrayNotHasKey(0, $map);
        self::assertSame(3, $map[1]['open_issues']);
        self::assertSame(9, $map[1]['events_last_7d']);
        self::assertNull($map[1]['last_ingest_at']);
        self::assertSame(0, $map[2]['open_issues']);
        self::assertSame(1, $map[2]['events_last_7d']);
        self::assertInstanceOf(DateTimeImmutable::class, $map[2]['last_ingest_at']);
    }

    private function project(int $id): Project
    {
        $project = new Project();
        $project->setName('P'.$id);
        $project->setSlug('p'.$id);

        $property = new ReflectionProperty(Project::class, 'id');
        $property->setValue($project, $id);

        return $project;
    }
}
