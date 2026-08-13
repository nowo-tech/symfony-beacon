<?php

declare(strict_types=1);

namespace App\Shared\Settings\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

trait InstanceSettingsOpsFields
{
    #[ORM\Column(options: ['default' => 0])]
    private int $retentionDays = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $retentionMaxEvents = 0;

    #[ORM\Column(options: ['default' => 120])]
    private int $ingestRateLimit = 120;

    #[ORM\Column(options: ['default' => 0])]
    private int $eventQuotaDaily = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $eventQuotaMonthly = 0;

    #[ORM\Column(options: ['default' => 20])]
    private int $notificationDeliveryHistoryLimit = 20;

    #[ORM\Column(options: ['default' => 5])]
    private int $notificationCircuitBreakerThreshold = 5;

    #[ORM\Column(options: ['default' => 0])]
    private int $notificationCircuitBreakerCooldownMinutes = 0;

    #[ORM\Column(options: ['default' => 2_097_152])]
    private int $envelopeMaxBytes = 2_097_152;

    /** Prometheus scrape Bearer token (encrypted at rest). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $metricsToken = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $metricsRequireToken = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $inboundEmailEnabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $inboundMailDomain = null;

    /** Inbound webhook + Reply-To signing secret (encrypted at rest). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $inboundWebhookSecret = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $allowPrivateUrls = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $allowAnonymousResolve = false;

    public function getRetentionDays(): int
    {
        return $this->retentionDays;
    }

    public function setRetentionDays(int $retentionDays): self
    {
        $this->retentionDays = max(0, $retentionDays);

        return $this;
    }

    public function getRetentionMaxEvents(): int
    {
        return $this->retentionMaxEvents;
    }

    public function setRetentionMaxEvents(int $retentionMaxEvents): self
    {
        $this->retentionMaxEvents = max(0, $retentionMaxEvents);

        return $this;
    }

    public function getIngestRateLimit(): int
    {
        return $this->ingestRateLimit;
    }

    public function setIngestRateLimit(int $ingestRateLimit): self
    {
        $this->ingestRateLimit = max(0, $ingestRateLimit);

        return $this;
    }

    public function getEventQuotaDaily(): int
    {
        return $this->eventQuotaDaily;
    }

    public function setEventQuotaDaily(int $eventQuotaDaily): self
    {
        $this->eventQuotaDaily = max(0, $eventQuotaDaily);

        return $this;
    }

    public function getEventQuotaMonthly(): int
    {
        return $this->eventQuotaMonthly;
    }

    public function setEventQuotaMonthly(int $eventQuotaMonthly): self
    {
        $this->eventQuotaMonthly = max(0, $eventQuotaMonthly);

        return $this;
    }

    public function getNotificationDeliveryHistoryLimit(): int
    {
        return $this->notificationDeliveryHistoryLimit;
    }

    public function setNotificationDeliveryHistoryLimit(int $notificationDeliveryHistoryLimit): self
    {
        $this->notificationDeliveryHistoryLimit = max(1, $notificationDeliveryHistoryLimit);

        return $this;
    }

    public function getNotificationCircuitBreakerThreshold(): int
    {
        return $this->notificationCircuitBreakerThreshold;
    }

    public function setNotificationCircuitBreakerThreshold(int $notificationCircuitBreakerThreshold): self
    {
        $this->notificationCircuitBreakerThreshold = max(1, $notificationCircuitBreakerThreshold);

        return $this;
    }

    public function getNotificationCircuitBreakerCooldownMinutes(): int
    {
        return $this->notificationCircuitBreakerCooldownMinutes;
    }

    public function setNotificationCircuitBreakerCooldownMinutes(int $notificationCircuitBreakerCooldownMinutes): self
    {
        $this->notificationCircuitBreakerCooldownMinutes = max(0, $notificationCircuitBreakerCooldownMinutes);

        return $this;
    }

    public function getEnvelopeMaxBytes(): int
    {
        return $this->envelopeMaxBytes;
    }

    public function setEnvelopeMaxBytes(int $envelopeMaxBytes): self
    {
        $this->envelopeMaxBytes = max(1, $envelopeMaxBytes);

        return $this;
    }

    public function getMetricsToken(): ?string
    {
        return $this->metricsToken;
    }

    public function setMetricsToken(?string $metricsToken): self
    {
        if (null === $metricsToken) {
            $this->metricsToken = null;

            return $this;
        }

        $trimmed = trim($metricsToken);
        $this->metricsToken = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function hasMetricsToken(): bool
    {
        return null !== $this->metricsToken && '' !== $this->metricsToken;
    }

    public function isMetricsRequireToken(): bool
    {
        return $this->metricsRequireToken;
    }

    public function setMetricsRequireToken(bool $metricsRequireToken): self
    {
        $this->metricsRequireToken = $metricsRequireToken;

        return $this;
    }

    public function isInboundEmailEnabled(): bool
    {
        return $this->inboundEmailEnabled;
    }

    public function setInboundEmailEnabled(bool $inboundEmailEnabled): self
    {
        $this->inboundEmailEnabled = $inboundEmailEnabled;

        return $this;
    }

    public function getInboundMailDomain(): ?string
    {
        return $this->inboundMailDomain;
    }

    public function setInboundMailDomain(?string $inboundMailDomain): self
    {
        if (null === $inboundMailDomain) {
            $this->inboundMailDomain = null;

            return $this;
        }

        $trimmed = trim($inboundMailDomain);
        $this->inboundMailDomain = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function getInboundWebhookSecret(): ?string
    {
        return $this->inboundWebhookSecret;
    }

    public function setInboundWebhookSecret(?string $inboundWebhookSecret): self
    {
        if (null === $inboundWebhookSecret) {
            $this->inboundWebhookSecret = null;

            return $this;
        }

        $trimmed = trim($inboundWebhookSecret);
        $this->inboundWebhookSecret = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function hasInboundWebhookSecret(): bool
    {
        return null !== $this->inboundWebhookSecret && '' !== $this->inboundWebhookSecret;
    }

    public function isAllowPrivateUrls(): bool
    {
        return $this->allowPrivateUrls;
    }

    public function setAllowPrivateUrls(bool $allowPrivateUrls): self
    {
        $this->allowPrivateUrls = $allowPrivateUrls;

        return $this;
    }

    public function isAllowAnonymousResolve(): bool
    {
        return $this->allowAnonymousResolve;
    }

    public function setAllowAnonymousResolve(bool $allowAnonymousResolve): self
    {
        $this->allowAnonymousResolve = $allowAnonymousResolve;

        return $this;
    }
}
