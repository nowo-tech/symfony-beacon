<?php

declare(strict_types=1);

namespace App\Shared\Settings\Service;

use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;

/**
 * Reads instance-wide operational defaults from {@see InstanceSettings} (singleton row).
 *
 * Replaces former env parameters for retention, ingest, quotas, metrics, inbound email,
 * notification SSRF, and hook mutation policy.
 */
final readonly class InstanceOpsDefaults
{
    public function __construct(
        private InstanceSettingsRepository $repository,
    ) {
    }

    public function settings(): InstanceSettings
    {
        return $this->repository->getOrCreate();
    }

    public function retentionDays(): int
    {
        return max(0, $this->settings()->getRetentionDays());
    }

    public function retentionMaxEvents(): int
    {
        return max(0, $this->settings()->getRetentionMaxEvents());
    }

    public function ingestRateLimit(): int
    {
        return max(0, $this->settings()->getIngestRateLimit());
    }

    public function eventQuotaDaily(): int
    {
        return max(0, $this->settings()->getEventQuotaDaily());
    }

    public function eventQuotaMonthly(): int
    {
        return max(0, $this->settings()->getEventQuotaMonthly());
    }

    public function deliveryHistoryLimit(): int
    {
        return max(1, $this->settings()->getNotificationDeliveryHistoryLimit());
    }

    public function circuitBreakerThreshold(): int
    {
        return max(1, $this->settings()->getNotificationCircuitBreakerThreshold());
    }

    public function circuitBreakerCooldownMinutes(): int
    {
        return max(0, $this->settings()->getNotificationCircuitBreakerCooldownMinutes());
    }

    public function envelopeMaxBytes(): int
    {
        return max(1, $this->settings()->getEnvelopeMaxBytes());
    }

    public function ingestRejectQueryAuth(): bool
    {
        return $this->settings()->isIngestRejectQueryAuth();
    }

    public function metricsToken(): string
    {
        return $this->settings()->getMetricsToken() ?? '';
    }

    public function metricsRequireToken(): bool
    {
        return $this->settings()->isMetricsRequireToken();
    }

    public function inboundEmailEnabled(): bool
    {
        return $this->settings()->isInboundEmailEnabled();
    }

    public function inboundMailDomain(): string
    {
        return $this->settings()->getInboundMailDomain() ?? '';
    }

    public function inboundWebhookSecret(): string
    {
        return $this->settings()->getInboundWebhookSecret() ?? '';
    }

    public function allowPrivateUrls(): bool
    {
        return $this->settings()->isAllowPrivateUrls();
    }

    public function allowAnonymousResolve(): bool
    {
        return $this->settings()->isAllowAnonymousResolve();
    }
}
