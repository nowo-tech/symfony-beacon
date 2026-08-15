<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Resolves effective governance limits (project override → instance default) and quota usage.
 *
 * Monthly quotas use the UTC calendar month (FR-004).
 * Daily/monthly event counts are cached briefly to avoid a COUNT on every ingest ACK + worker.
 */
final readonly class ProjectGovernanceResolver
{
    public const float APPROACHING_QUOTA_RATIO = 0.8;

    /** Short TTL: slight overshoot under burst is acceptable for soft quota gates. */
    private const int QUOTA_COUNT_TTL_SECONDS = 30;

    public function __construct(
        private EventRepository $eventRepository,
        private InstanceOpsDefaults $opsDefaults,
        private CacheInterface $cache,
    ) {
    }

    public function effectiveRetentionDays(Project $project): int
    {
        return $project->getRetentionDays() ?? $this->opsDefaults->retentionDays();
    }

    public function effectiveRetentionMaxEvents(Project $project): int
    {
        return $project->getRetentionMaxEvents() ?? $this->opsDefaults->retentionMaxEvents();
    }

    public function effectiveIngestRateLimit(Project $project): int
    {
        return $project->getIngestRateLimitPerMinute() ?? $this->opsDefaults->ingestRateLimit();
    }

    public function effectiveEventQuotaDaily(Project $project): int
    {
        return $project->getEventQuotaDaily() ?? $this->opsDefaults->eventQuotaDaily();
    }

    public function effectiveEventQuotaMonthly(Project $project): int
    {
        return $project->getEventQuotaMonthly() ?? $this->opsDefaults->eventQuotaMonthly();
    }

    public function eventsReceivedToday(Project $project): int
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return $this->eventRepository->countReceivedTodayForProject($project);
        }

        $dayKey = (new DateTimeImmutable('today'))->format('Ymd');

        return $this->cachedCount(
            'gov_quota_daily_'.$projectId.'_'.$dayKey,
            fn (): int => $this->eventRepository->countReceivedTodayForProject($project),
        );
    }

    public function eventsReceivedThisMonth(Project $project): int
    {
        $projectId = $project->getId();
        $since = self::utcMonthStart();
        if (null === $projectId) {
            return $this->eventRepository->countReceivedSinceForProject($project, $since);
        }

        return $this->cachedCount(
            'gov_quota_monthly_'.$projectId.'_'.$since->format('Ym'),
            fn (): int => $this->eventRepository->countReceivedSinceForProject($project, $since),
        );
    }

    /**
     * Start of the current UTC calendar month (inclusive lower bound for monthly quota).
     */
    public static function utcMonthStart(?DateTimeImmutable $now = null): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $now ??= new DateTimeImmutable('now', $utc);

        return $now->setTimezone($utc)->modify('first day of this month')->setTime(0, 0, 0);
    }

    /**
     * True when a daily quota is configured and today's count is at or above 80%.
     */
    public function isApproachingDailyQuota(Project $project): bool
    {
        return $this->isApproachingQuota(
            $this->effectiveEventQuotaDaily($project),
            $this->eventsReceivedToday($project),
        );
    }

    /**
     * True when a monthly quota is configured and this UTC month's count is at or above 80%.
     */
    public function isApproachingMonthlyQuota(Project $project): bool
    {
        return $this->isApproachingQuota(
            $this->effectiveEventQuotaMonthly($project),
            $this->eventsReceivedThisMonth($project),
        );
    }

    /**
     * True when a daily quota is configured and today's count has reached it.
     */
    public function isDailyQuotaExceeded(Project $project): bool
    {
        return $this->isQuotaExceeded(
            $this->effectiveEventQuotaDaily($project),
            $this->eventsReceivedToday($project),
        );
    }

    /**
     * True when a monthly quota is configured and this UTC month's count has reached it.
     */
    public function isMonthlyQuotaExceeded(Project $project): bool
    {
        return $this->isQuotaExceeded(
            $this->effectiveEventQuotaMonthly($project),
            $this->eventsReceivedThisMonth($project),
        );
    }

    /**
     * Instance defaults exposed to Settings UI (empty field = inherit).
     *
     * @return array{
     *     retention_days: int,
     *     retention_max_events: int,
     *     ingest_rate_limit: int,
     *     event_quota_daily: int,
     *     event_quota_monthly: int
     * }
     */
    public function envDefaults(): array
    {
        return [
            'retention_days' => $this->opsDefaults->retentionDays(),
            'retention_max_events' => $this->opsDefaults->retentionMaxEvents(),
            'ingest_rate_limit' => $this->opsDefaults->ingestRateLimit(),
            'event_quota_daily' => $this->opsDefaults->eventQuotaDaily(),
            'event_quota_monthly' => $this->opsDefaults->eventQuotaMonthly(),
        ];
    }

    /**
     * @param callable(): int $compute
     */
    private function cachedCount(string $key, callable $compute): int
    {
        return (int) $this->cache->get($key, static function (ItemInterface $item) use ($compute): int {
            $item->expiresAfter(self::QUOTA_COUNT_TTL_SECONDS);

            return $compute();
        });
    }

    private function isApproachingQuota(int $quota, int $count): bool
    {
        if ($quota < 1) {
            return false;
        }

        return $count >= (int) ceil($quota * self::APPROACHING_QUOTA_RATIO);
    }

    private function isQuotaExceeded(int $quota, int $count): bool
    {
        if ($quota < 1) {
            return false;
        }

        return $count >= $quota;
    }
}
