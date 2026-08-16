<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Entity;

use App\Shared\Settings\Entity\InstanceSettings;
use PHPUnit\Framework\TestCase;

final class InstanceSettingsFieldsTest extends TestCase
{
    public function testMailerFieldsNormalizeMaskAndFallback(): void
    {
        $settings = InstanceSettings::defaults();
        self::assertFalse($settings->hasMailerDsn());
        self::assertNull($settings->maskedMailerDsn());
        self::assertSame(InstanceSettings::DEFAULT_MAILER_FROM, $settings->getEffectiveMailerFrom());

        $settings->setMailerDsn('smtp://user:secret@mail.example:587');
        self::assertTrue($settings->hasMailerDsn());
        self::assertStringStartsWith('smtp://us', (string) $settings->maskedMailerDsn());

        $settings->setMailerDsn('tokenonly');
        self::assertSame(str_repeat('•', 9), $settings->maskedMailerDsn());

        $settings->setMailerDsn('smtp://');
        self::assertSame('smtp://••••', $settings->maskedMailerDsn());

        $settings->setMailerDsn('   ');
        self::assertFalse($settings->hasMailerDsn());

        $settings->setMailerFrom('  ops@example.com ');
        self::assertSame('ops@example.com', $settings->getMailerFrom());
        self::assertSame('ops@example.com', $settings->getEffectiveMailerFrom());
        $settings->setMailerFrom(null);
        self::assertSame(InstanceSettings::DEFAULT_MAILER_FROM, $settings->getEffectiveMailerFrom());
    }

    public function testMercureFieldsNormalizeAndReportSecrets(): void
    {
        $settings = InstanceSettings::defaults();
        self::assertFalse($settings->isMercureEnabled());
        self::assertFalse($settings->hasMercureJwtSecret());

        $settings
            ->setMercureEnabled(true)
            ->setMercureUrl(' https://mercure.internal/.well-known/mercure ')
            ->setMercurePublicUrl(' https://mercure.example/.well-known/mercure ')
            ->setMercureJwtSecret(' top-secret ');

        self::assertTrue($settings->isMercureEnabled());
        self::assertSame('https://mercure.internal/.well-known/mercure', $settings->getMercureUrl());
        self::assertSame('https://mercure.example/.well-known/mercure', $settings->getMercurePublicUrl());
        self::assertSame('top-secret', $settings->getMercureJwtSecret());
        self::assertTrue($settings->hasMercureJwtSecret());

        $settings->setMercureUrl('   ')->setMercurePublicUrl(null)->setMercureJwtSecret(' ');
        self::assertNull($settings->getMercureUrl());
        self::assertNull($settings->getMercurePublicUrl());
        self::assertNull($settings->getMercureJwtSecret());
        self::assertFalse($settings->hasMercureJwtSecret());
    }

    public function testOpsFieldsClampNormalizeAndToggleFlags(): void
    {
        $settings = InstanceSettings::defaults();
        $settings
            ->setRetentionDays(-10)
            ->setRetentionMaxEvents(-5)
            ->setIngestRateLimit(-1)
            ->setEventQuotaDaily(-1)
            ->setEventQuotaMonthly(-1)
            ->setNotificationDeliveryHistoryLimit(0)
            ->setNotificationCircuitBreakerThreshold(0)
            ->setNotificationCircuitBreakerCooldownMinutes(-3)
            ->setEnvelopeMaxBytes(0)
            ->setMetricsToken('  scrape-token  ')
            ->setMetricsRequireToken(false)
            ->setInboundEmailEnabled(true)
            ->setInboundMailDomain(' inbound.example.test ')
            ->setInboundWebhookSecret(' hook-secret ')
            ->setAllowPrivateUrls(true)
            ->setAllowAnonymousResolve(true);

        self::assertSame(0, $settings->getRetentionDays());
        self::assertSame(0, $settings->getRetentionMaxEvents());
        self::assertSame(0, $settings->getIngestRateLimit());
        self::assertSame(0, $settings->getEventQuotaDaily());
        self::assertSame(0, $settings->getEventQuotaMonthly());
        self::assertSame(1, $settings->getNotificationDeliveryHistoryLimit());
        self::assertSame(1, $settings->getNotificationCircuitBreakerThreshold());
        self::assertSame(0, $settings->getNotificationCircuitBreakerCooldownMinutes());
        self::assertSame(1, $settings->getEnvelopeMaxBytes());
        self::assertSame('scrape-token', $settings->getMetricsToken());
        self::assertTrue($settings->hasMetricsToken());
        self::assertFalse($settings->isMetricsRequireToken());
        self::assertTrue($settings->isInboundEmailEnabled());
        self::assertSame('inbound.example.test', $settings->getInboundMailDomain());
        self::assertSame('hook-secret', $settings->getInboundWebhookSecret());
        self::assertTrue($settings->hasInboundWebhookSecret());
        self::assertTrue($settings->isAllowPrivateUrls());
        self::assertTrue($settings->isAllowAnonymousResolve());

        $settings->setMetricsToken(' ')->setInboundMailDomain('  ')->setInboundWebhookSecret(null);
        self::assertNull($settings->getMetricsToken());
        self::assertFalse($settings->hasMetricsToken());
        self::assertNull($settings->getInboundMailDomain());
        self::assertNull($settings->getInboundWebhookSecret());
        self::assertFalse($settings->hasInboundWebhookSecret());
    }
}
