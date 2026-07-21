<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;

/**
 * Resolves effective governance limits (project override → env default) and quota usage.
 */
final readonly class ProjectGovernanceResolver
{
    public const float APPROACHING_QUOTA_RATIO = 0.8;

    public function __construct(
        private EventRepository $eventRepository,
        private int $defaultRetentionDays,
        private int $defaultRetentionMaxEvents,
        private int $defaultIngestRateLimit,
        private int $defaultEventQuotaDaily,
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

    public function eventsReceivedToday(Project $project): int
    {
        return $this->eventRepository->countReceivedTodayForProject($project);
    }

    /**
     * True when a daily quota is configured and today's count is at or above 80%.
     */
    public function isApproachingDailyQuota(Project $project): bool
    {
        $quota = $this->effectiveEventQuotaDaily($project);
        if ($quota < 1) {
            return false;
        }

        $count = $this->eventsReceivedToday($project);

        return $count >= (int) ceil($quota * self::APPROACHING_QUOTA_RATIO);
    }

    /**
     * True when a daily quota is configured and today's count has reached it.
     */
    public function isDailyQuotaExceeded(Project $project): bool
    {
        $quota = $this->effectiveEventQuotaDaily($project);
        if ($quota < 1) {
            return false;
        }

        return $this->eventsReceivedToday($project) >= $quota;
    }

    /**
     * Env defaults exposed to Settings UI (empty field = inherit).
     *
     * @return array{retention_days: int, retention_max_events: int, ingest_rate_limit: int, event_quota_daily: int}
     */
    public function envDefaults(): array
    {
        return [
            'retention_days' => $this->defaultRetentionDays,
            'retention_max_events' => $this->defaultRetentionMaxEvents,
            'ingest_rate_limit' => $this->defaultIngestRateLimit,
            'event_quota_daily' => $this->defaultEventQuotaDaily,
        ];
    }
}
