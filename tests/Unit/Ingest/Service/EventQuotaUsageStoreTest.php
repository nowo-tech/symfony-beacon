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

    public function testUnpersistedProjectBypassesCacheAndRecord(): void
    {
        $project = new Project()->setName('Q')->setSlug('q');
        $events = $this->createMock(EventRepository::class);
        $events->expects(self::exactly(2))->method('countReceivedTodayForProject')->willReturn(3);
        $events->expects(self::once())->method('countReceivedSinceForProject')->willReturn(9);

        $store = new EventQuotaUsageStore($events, new ArrayAdapter());
        self::assertSame(3, $store->eventsReceivedToday($project));
        self::assertSame(9, $store->eventsReceivedThisMonth($project));
        $store->recordAcceptedEvent($project, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        self::assertSame(3, $store->eventsReceivedToday($project));
    }

    public function testRedisPathSeedsOnceThenIncrementsWithoutASecondRead(): void
    {
        $project = new Project()->setName('Q')->setSlug('q');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);

        $events = $this->createMock(EventRepository::class);
        $events->expects(self::once())->method('countReceivedTodayForProject')->willReturn(4);
        $events->expects(self::once())->method('countReceivedSinceForProject')->willReturn(8);

        $redis = new MemoryQuotaRedis();
        $store = new EventQuotaUsageStore($events, new ArrayAdapter(), $redis);

        self::assertSame(4, $store->eventsReceivedToday($project));
        self::assertSame(4, $store->eventsReceivedToday($project));
        self::assertSame(8, $store->eventsReceivedThisMonth($project));

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $store->recordAcceptedEvent($project, $now);
        $store->recordAcceptedEvent($project, $now);

        self::assertSame(6, $store->eventsReceivedToday($project));
        self::assertSame(10, $store->eventsReceivedThisMonth($project));
        self::assertNotEmpty($redis->sets);
        foreach ($redis->sets as $set) {
            self::assertArrayHasKey('EXAT', $set['options']);
        }
        self::assertSame([], $redis->expires);
    }

    public function testRedisIncrementSetsExpiryWhenTheCounterDidNotExist(): void
    {
        $project = new Project()->setName('Q')->setSlug('q');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);

        $events = $this->createStub(EventRepository::class);
        $redis = new MemoryQuotaRedis();
        $store = new EventQuotaUsageStore($events, new ArrayAdapter(), $redis);

        $store->recordAcceptedEvent($project, new DateTimeImmutable('now', new DateTimeZone('UTC')));

        self::assertCount(2, $redis->expires);
        self::assertSame(1, $store->eventsReceivedToday($project));
        self::assertSame(1, $store->eventsReceivedThisMonth($project));
    }

    public function testRedisPathKeepsTheWinnerWhenSetLosesTheRace(): void
    {
        $project = new Project()->setName('Q')->setSlug('q');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);

        $events = $this->createMock(EventRepository::class);
        $events->expects(self::once())->method('countReceivedTodayForProject')->willReturn(1);

        $redis = new MemoryQuotaRedis();
        $redis->loseNextSet = true;
        $redis->raceValue = '12';
        $store = new EventQuotaUsageStore($events, new ArrayAdapter(), $redis);

        self::assertSame(12, $store->eventsReceivedToday($project));
    }
}

final class MemoryQuotaRedis implements \App\Ingest\Service\QuotaRedis
{
    /** @var list<array{key: string, value: string, options: array<string, mixed>}> */
    public array $sets = [];

    /** @var array<string, string> */
    public array $values = [];

    /** @var array<string, int> */
    public array $expires = [];

    public bool $loseNextSet = false;

    public string $raceValue = '0';

    public function set(string $key, string $value, array $options): mixed
    {
        $this->sets[] = ['key' => $key, 'value' => $value, 'options' => $options];
        if ($this->loseNextSet) {
            $this->loseNextSet = false;
            $this->values[$key] = $this->raceValue;

            return false;
        }

        if (\in_array('NX', $options, true) && isset($this->values[$key])) {
            return false;
        }

        $this->values[$key] = $value;

        return true;
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? false;
    }

    public function incr(string $key): int|false
    {
        $next = (int) ($this->values[$key] ?? 0) + 1;
        $this->values[$key] = (string) $next;

        return $next;
    }

    public function expireAt(string $key, int $timestamp): bool
    {
        $this->expires[$key] = $timestamp;

        return true;
    }
}
