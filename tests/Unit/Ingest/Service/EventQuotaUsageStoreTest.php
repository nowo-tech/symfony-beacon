<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\Service;

use App\Ingest\Service\EventQuotaUsageStore;
use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class EventQuotaUsageStoreTest extends TestCase
{
    public function testSeedsFromRepositoryThenIncrementsFromCache(): void
    {
        $project = new Project()->setName('Q')->setSlug('q');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);

        $events = $this->createMock(EventRepository::class);
        $events->expects(self::once())->method('countReceivedTodayForProject')->willReturn(10);
        $events->expects(self::once())->method('countReceivedSinceForProject')->willReturn(40);

        $store = new EventQuotaUsageStore($events, new ArrayAdapter());
        self::assertSame(10, $store->eventsReceivedToday($project));
        self::assertSame(10, $store->eventsReceivedToday($project));
        self::assertSame(40, $store->eventsReceivedThisMonth($project));

        $store->recordAcceptedEvent($project, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        self::assertSame(11, $store->eventsReceivedToday($project));
        self::assertSame(41, $store->eventsReceivedThisMonth($project));
    }
}
