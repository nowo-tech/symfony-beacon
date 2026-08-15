<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project;

use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use App\Project\Service\ProjectGovernanceResolver;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ProjectGovernanceResolverTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testUtcMonthStart(): void
    {
        $now = new DateTimeImmutable('2026-08-15 18:22:11', new DateTimeZone('Europe/Madrid'));
        $start = ProjectGovernanceResolver::utcMonthStart($now);

        self::assertSame('2026-08-01 00:00:00', $start->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $start->getTimezone()->getName());
    }

    public function testEffectiveLimitsPreferProjectOverrides(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setRetentionDays(30);
            $settings->setRetentionMaxEvents(1000);
            $settings->setIngestRateLimit(60);
            $settings->setEventQuotaDaily(100);
            $settings->setEventQuotaMonthly(1000);
        });

        $events = $this->createStub(EventRepository::class);
        $resolver = new ProjectGovernanceResolver($events, $ops, new ArrayAdapter());

        $project = new Project();
        $project->setRetentionDays(7);
        $project->setRetentionMaxEvents(50);
        $project->setIngestRateLimitPerMinute(10);
        $project->setEventQuotaDaily(20);
        $project->setEventQuotaMonthly(200);

        self::assertSame(7, $resolver->effectiveRetentionDays($project));
        self::assertSame(50, $resolver->effectiveRetentionMaxEvents($project));
        self::assertSame(10, $resolver->effectiveIngestRateLimit($project));
        self::assertSame(20, $resolver->effectiveEventQuotaDaily($project));
        self::assertSame(200, $resolver->effectiveEventQuotaMonthly($project));
        self::assertSame(
            [
                'retention_days' => 30,
                'retention_max_events' => 1000,
                'ingest_rate_limit' => 60,
                'event_quota_daily' => 100,
                'event_quota_monthly' => 1000,
            ],
            $resolver->envDefaults(),
        );
    }

    public function testQuotaApproachingAndExceeded(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setEventQuotaDaily(100);
            $settings->setEventQuotaMonthly(1000);
        });

        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(80);
        $events->method('countReceivedSinceForProject')->willReturn(1000);

        $resolver = new ProjectGovernanceResolver($events, $ops, new ArrayAdapter());
        $project = new Project();

        self::assertTrue($resolver->isApproachingDailyQuota($project));
        self::assertFalse($resolver->isDailyQuotaExceeded($project));
        self::assertTrue($resolver->isApproachingMonthlyQuota($project));
        self::assertTrue($resolver->isMonthlyQuotaExceeded($project));
    }

    public function testZeroQuotaNeverApproachesOrExceeds(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setEventQuotaDaily(0);
            $settings->setEventQuotaMonthly(0);
        });

        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(999);
        $events->method('countReceivedSinceForProject')->willReturn(999);

        $resolver = new ProjectGovernanceResolver($events, $ops, new ArrayAdapter());
        $project = new Project();

        self::assertFalse($resolver->isApproachingDailyQuota($project));
        self::assertFalse($resolver->isDailyQuotaExceeded($project));
        self::assertFalse($resolver->isApproachingMonthlyQuota($project));
        self::assertFalse($resolver->isMonthlyQuotaExceeded($project));
    }
}
