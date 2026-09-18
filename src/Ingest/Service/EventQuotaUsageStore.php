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
        private ?QuotaRedis $quotaRedis = null,
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
        $expiresAt = $now->modify('tomorrow')->setTime(0, 0, 0);

        return $this->read($key, $expiresAt, fn (): int => $this->eventRepository->countReceivedTodayForProject($project));
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
        $expiresAt = $now->modify('first day of next month')->setTime(0, 0, 0);

        return $this->read(
            $key,
            $expiresAt,
            fn (): int => $this->eventRepository->countReceivedSinceForProject(
                $project,
                ProjectGovernanceResolver::utcMonthStart($now),
            ),
        );
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
        if ($this->quotaRedis instanceof QuotaRedis) {
            $full = $this->redisKey($key);
            $next = $this->quotaRedis->incr($full);
            if (1 === $next) {
                $this->quotaRedis->expireAt($full, $expiresAt->getTimestamp());
            }

            return;
        }

        $item = $this->cache->getItem($key);
        $value = $item->isHit() ? max(0, (int) $item->get()) + 1 : 1;
        $item->set($value);
        $item->expiresAt($expiresAt);
        $this->cache->save($item);
    }

    /**
     * @param callable(): int $seed
     */
    private function read(string $key, DateTimeImmutable $expiresAt, callable $seed): int
    {
        if ($this->quotaRedis instanceof QuotaRedis) {
            $full = $this->redisKey($key);
            $existing = $this->quotaRedis->get($full);
            if (is_numeric($existing)) {
                return max(0, (int) $existing);
            }

            $count = max(0, $seed());
            $stored = $this->quotaRedis->set($full, (string) $count, [
                'NX',
                'EXAT' => $expiresAt->getTimestamp(),
            ]);
            if (!$stored) {
                $existing = $this->quotaRedis->get($full);

                return is_numeric($existing) ? max(0, (int) $existing) : $count;
            }

            return $count;
        }

        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return max(0, (int) $item->get());
        }

        $count = max(0, $seed());
        $item->set($count);
        $item->expiresAt($expiresAt);
        $this->cache->save($item);

        return $count;
    }

    private function redisKey(string $key): string
    {
        return 'symfony-beacon.'.$key;
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
