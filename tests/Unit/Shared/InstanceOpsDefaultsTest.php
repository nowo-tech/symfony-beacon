<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use PHPUnit\Framework\TestCase;

final class InstanceOpsDefaultsTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testClampsAndReadsSettings(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setRetentionDays(-5);
            $settings->setRetentionMaxEvents(-1);
            $settings->setIngestRateLimit(-2);
            $settings->setEventQuotaDaily(-3);
            $settings->setEventQuotaMonthly(-4);
            $settings->setNotificationDeliveryHistoryLimit(0);
            $settings->setNotificationCircuitBreakerThreshold(0);
            $settings->setNotificationCircuitBreakerCooldownMinutes(-1);
            $settings->setEnvelopeMaxBytes(0);
            $settings->setMetricsToken(' tok ');
            $settings->setMetricsRequireToken(true);
            $settings->setInboundEmailEnabled(true);
            $settings->setInboundMailDomain(' inbound.example ');
            $settings->setInboundWebhookSecret(' secret ');
            $settings->setAllowPrivateUrls(true);
            $settings->setAllowAnonymousResolve(true);
        });

        self::assertSame(0, $ops->retentionDays());
        self::assertSame(0, $ops->retentionMaxEvents());
        self::assertSame(0, $ops->ingestRateLimit());
        self::assertSame(0, $ops->eventQuotaDaily());
        self::assertSame(0, $ops->eventQuotaMonthly());
        self::assertSame(1, $ops->deliveryHistoryLimit());
        self::assertSame(1, $ops->circuitBreakerThreshold());
        self::assertSame(0, $ops->circuitBreakerCooldownMinutes());
        self::assertSame(1, $ops->envelopeMaxBytes());
        self::assertSame('tok', $ops->metricsToken());
        self::assertTrue($ops->metricsRequireToken());
        self::assertTrue($ops->inboundEmailEnabled());
        self::assertSame('inbound.example', $ops->inboundMailDomain());
        self::assertSame('secret', $ops->inboundWebhookSecret());
        self::assertTrue($ops->allowPrivateUrls());
        self::assertTrue($ops->allowAnonymousResolve());
    }
}
