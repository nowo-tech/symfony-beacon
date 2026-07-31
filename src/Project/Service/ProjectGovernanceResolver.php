<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves effective governance limits (project override → env default) and quota usage.
 *
 * Monthly quotas use the UTC calendar month (FR-004).
 */
final readonly class ProjectGovernanceResolver
{
    public const float APPROACHING_QUOTA_RATIO = 0.8;

    public function __construct(
        private EventRepository $eventRepository,
        #[Autowire('%beacon.retention_days%')]
        private int $defaultRetentionDays,
        #[Autowire('%beacon.retention_max_events%')]
        private int $defaultRetentionMaxEvents,
        #[Autowire('%beacon.ingest_rate_limit%')]
        private int $defaultIngestRateLimit,
        #[Autowire('%beacon.event_quota_daily%')]
        private int $defaultEventQuotaDaily,
        #[Autowire('%beacon.event_quota_monthly%')]
        private int $defaultEventQuotaMonthly,
    ) {
    }

    public function effectiveRetentionDays(Project $project): int
    {
        return $project->getRetentionDays() ?? $this->defaultRetentionDays;
    }

    public function effectiveRetentionMaxEvents(Project $project): int
    {
        return $project->getRetentionMaxEvents() ?? $this->defaultRetentionMaxEvents;
    }

    public function effectiveIngestRateLimit(Project $project): int
    {
        return $project->getIngestRateLimitPerMinute() ?? $this->defaultIngestRateLimit;
    }

    public function effectiveEventQuotaDaily(Project $project): int
    {
        return $project->getEventQuotaDaily() ?? $this->defaultEventQuotaDaily;
    }

    public function effectiveEventQuotaMonthly(Project $project): int
    {
        return $project->getEventQuotaMonthly() ?? $this->defaultEventQuotaMonthly;
    }

    public function eventsReceivedToday(Project $project): int
    {
        return $this->eventRepository->countReceivedTodayForProject($project);
    }

    public function eventsReceivedThisMonth(Project $project): int
    {
        return $this->eventRepository->countReceivedSinceForProject($project, self::utcMonthStart());
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
     * Env defaults exposed to Settings UI (empty field = inherit).
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
            'retention_days' => $this->defaultRetentionDays,
            'retention_max_events' => $this->defaultRetentionMaxEvents,
            'ingest_rate_limit' => $this->defaultIngestRateLimit,
            'event_quota_daily' => $this->defaultEventQuotaDaily,
            'event_quota_monthly' => $this->defaultEventQuotaMonthly,
        ];
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
