<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use App\Project\Service\ProjectGovernanceResolver;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cached daily/monthly event counts for ingest quota checks.
 *
 * Seeds from {@see EventRepository} on cache miss, then increments on successful
 * Envelope writes so the hot path avoids repeated COUNT(*) under burst.
 * Counters expire at the end of the UTC day / month; retention deletes may leave
 * a slightly high cache until expiry (fail-closed for quotas).
 */
final readonly class EventQuotaUsageStore
{
    public function __construct(
        private EventRepository $eventRepository,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function eventsReceivedToday(Project $project): int
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return $this->eventRepository->countReceivedTodayForProject($project);
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $key = $this->dailyKey($projectId, $now);
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return max(0, (int) $item->get());
        }

        $count = $this->eventRepository->countReceivedTodayForProject($project);
        $item->set($count);
        $item->expiresAt($now->modify('tomorrow')->setTime(0, 0, 0));
        $this->cache->save($item);

        return $count;
    }

    public function eventsReceivedThisMonth(Project $project): int
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return $this->eventRepository->countReceivedSinceForProject(
                $project,
                ProjectGovernanceResolver::utcMonthStart(),
            );
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $key = $this->monthlyKey($projectId, $now);
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return max(0, (int) $item->get());
        }

        $count = $this->eventRepository->countReceivedSinceForProject(
            $project,
            ProjectGovernanceResolver::utcMonthStart($now),
        );
        $item->set($count);
        $item->expiresAt($now->modify('first day of next month')->setTime(0, 0, 0));
        $this->cache->save($item);

        return $count;
    }

    /**
     * Bump cached counters after a new event is accepted (not skipped).
     */
    public function recordAcceptedEvent(Project $project, DateTimeImmutable $receivedAt): void
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return;
        }

        $receivedAt = $receivedAt->setTimezone(new DateTimeZone('UTC'));
        $this->bump($this->dailyKey($projectId, $receivedAt), $receivedAt->modify('tomorrow')->setTime(0, 0, 0));
        $this->bump(
            $this->monthlyKey($projectId, $receivedAt),
            $receivedAt->modify('first day of next month')->setTime(0, 0, 0),
        );
    }

    private function bump(string $key, DateTimeImmutable $expiresAt): void
    {
        $item = $this->cache->getItem($key);
        $value = $item->isHit() ? max(0, (int) $item->get()) + 1 : 1;
        $item->set($value);
        $item->expiresAt($expiresAt);
        $this->cache->save($item);
    }

    private function dailyKey(int $projectId, DateTimeImmutable $at): string
    {
        return 'beacon.quota.daily.'.$projectId.'.'.$at->format('Y-m-d');
    }

    private function monthlyKey(int $projectId, DateTimeImmutable $at): string
    {
        return 'beacon.quota.monthly.'.$projectId.'.'.$at->format('Y-m');
    }
}
