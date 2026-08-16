<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops\Service;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\WebPushClientFactory;
use App\Ops\Service\BeaconDogfoodDiagnostics;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BeaconDogfoodDiagnosticsTest extends TestCase
{
    public function testCheckOnlyWarnsWhenNoPushSubscriptions(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getName')->willReturn('Symfony Beacon');
        $project->method('getUuid')->willReturn('proj-uuid');

        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('findOneByIngestPath')->willReturn($project);

        $push = $this->createMock(PushSubscriptionRepository::class);
        $push->method('countAll')->willReturn(0);

        $webPush = $this->createMock(WebPushClientFactory::class);
        $webPush->method('isConfigured')->willReturn(true);

        $diagnostics = new BeaconDogfoodDiagnostics(
            $projects,
            $this->createMock(EventRepository::class),
            $push,
            $webPush,
            $this->createMock(EntityManagerInterface::class),
        );

        $report = $diagnostics->diagnose('proj-uuid', null, true, 0);

        self::assertTrue($report->projectFound);
        self::assertSame(0, $report->pushSubscriptionCount);
        self::assertFalse($report->priorIssueExisted);
        self::assertNotEmpty($report->warnings);
        self::assertStringContainsString('push_subscription', $report->warnings[0]);
    }

    public function testWarnsWhenIssueAlreadyHadEvents(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getName')->willReturn('Symfony Beacon');
        $project->method('getUuid')->willReturn('proj-uuid');

        $issue = $this->createMock(Issue::class);
        $issue->method('getUuid')->willReturn('issue-uuid');
        $issue->method('getEventCount')->willReturn(5);

        $event = $this->createMock(Event::class);
        $event->method('getEventId')->willReturn('evt-1');
        $event->method('getIssue')->willReturn($issue);

        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('findOneByIngestPath')->willReturn($project);

        $events = $this->createMock(EventRepository::class);
        $events->method('findOneByEventId')->willReturn($event);

        $push = $this->createMock(PushSubscriptionRepository::class);
        $push->method('countAll')->willReturn(0);

        $webPush = $this->createMock(WebPushClientFactory::class);
        $webPush->method('isConfigured')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('clear');

        $diagnostics = new BeaconDogfoodDiagnostics(
            $projects,
            $events,
            $push,
            $webPush,
            $em,
        );

        $report = $diagnostics->diagnose('proj-uuid', 'evt-1', false, 0);

        self::assertTrue($report->priorIssueExisted);
        self::assertSame(4, $report->priorEventCount);
        self::assertTrue($report->eventPersisted);
        $joined = implode("\n", $report->warnings);
        self::assertStringContainsString('already existed', $joined);
        self::assertStringContainsString('VAPID', $joined);
        self::assertStringContainsString('push_subscription', $joined);
    }

    public function testPersistedNewIssueWithoutWarningsWhenPushReady(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getName')->willReturn('Symfony Beacon');
        $project->method('getUuid')->willReturn('proj-uuid');

        $issue = $this->createMock(Issue::class);
        $issue->method('getUuid')->willReturn('new-issue');
        $issue->method('getEventCount')->willReturn(1);

        $event = $this->createMock(Event::class);
        $event->method('getEventId')->willReturn('evt-2');
        $event->method('getIssue')->willReturn($issue);

        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('findOneByIngestPath')->willReturn($project);

        $events = $this->createMock(EventRepository::class);
        $events->method('findOneByEventId')->willReturn($event);

        $push = $this->createMock(PushSubscriptionRepository::class);
        $push->method('countAll')->willReturn(2);

        $webPush = $this->createMock(WebPushClientFactory::class);
        $webPush->method('isConfigured')->willReturn(true);

        $diagnostics = new BeaconDogfoodDiagnostics(
            $projects,
            $events,
            $push,
            $webPush,
            $this->createMock(EntityManagerInterface::class),
        );

        $report = $diagnostics->diagnose('proj-uuid', 'evt-2', false, 0);

        self::assertTrue($report->eventPersisted);
        self::assertFalse($report->priorIssueExisted);
        self::assertSame('new-issue', $report->issueUuid);
        self::assertSame([], $report->warnings);
    }
}
