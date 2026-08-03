<?php

declare(strict_types=1);

namespace App\Shared\Settings\Entity;

use App\Identity\Entity\User;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\AuditableInterface;
use Nowo\AuditKitBundle\Model\TimestampableTrait;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Singleton row for instance-wide operator settings (ROLE_ADMIN).
 */
#[ORM\Entity(repositoryClass: InstanceSettingsRepository::class)]
#[ORM\Table(name: 'instance_settings')]
class InstanceSettings implements AuditableInterface
{
    use TimestampableTrait;

    public const DEFAULT_MAILER_FROM = 'beacon@localhost';
    public const DEFAULT_RETENTION_DAYS = 0;
    public const DEFAULT_RETENTION_MAX_EVENTS = 0;
    public const DEFAULT_INGEST_RATE_LIMIT = 120;
    public const DEFAULT_EVENT_QUOTA_DAILY = 0;
    public const DEFAULT_EVENT_QUOTA_MONTHLY = 0;
    public const DEFAULT_DELIVERY_HISTORY_LIMIT = 20;
    public const DEFAULT_CIRCUIT_BREAKER_THRESHOLD = 5;
    public const DEFAULT_CIRCUIT_BREAKER_COOLDOWN_MINUTES = 0;
    public const DEFAULT_ENVELOPE_MAX_BYTES = 2_097_152;
    public const DEFAULT_INGEST_REJECT_QUERY_AUTH = true;
    public const DEFAULT_METRICS_REQUIRE_TOKEN = false;
    public const DEFAULT_INBOUND_EMAIL_ENABLED = false;
    public const DEFAULT_ALLOW_PRIVATE_URLS = false;
    public const DEFAULT_ALLOW_ANONYMOUS_RESOLVE = false;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    /** Symfony Mailer DSN (encrypted at rest via doctrine-encrypt-bundle). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mailerDsn = null;

    /** Mail From address (encrypted at rest). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mailerFrom = null;

    /** When set, the first-run setup wizard is considered finished / dismissed. */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $setupCompletedAt = null;

    /** When true, members may receive Mercure live alerts for new issues. */
    #[ORM\Column(options: ['default' => false])]
    private bool $mercureEnabled = false;

    /** Optional publish URL override (encrypted; empty = use MERCURE_URL env). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mercureUrl = null;

    /** Optional browser hub URL override (encrypted; empty = use MERCURE_PUBLIC_URL env). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mercurePublicUrl = null;

    /** Optional JWT HMAC secret override (encrypted; empty = use MERCURE_JWT_SECRET env). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mercureJwtSecret = null;

    #[ORM\Column(options: ['default' => self::DEFAULT_RETENTION_DAYS])]
    private int $retentionDays = self::DEFAULT_RETENTION_DAYS;

    #[ORM\Column(options: ['default' => self::DEFAULT_RETENTION_MAX_EVENTS])]
    private int $retentionMaxEvents = self::DEFAULT_RETENTION_MAX_EVENTS;

    #[ORM\Column(options: ['default' => self::DEFAULT_INGEST_RATE_LIMIT])]
    private int $ingestRateLimit = self::DEFAULT_INGEST_RATE_LIMIT;

    #[ORM\Column(options: ['default' => self::DEFAULT_EVENT_QUOTA_DAILY])]
    private int $eventQuotaDaily = self::DEFAULT_EVENT_QUOTA_DAILY;

    #[ORM\Column(options: ['default' => self::DEFAULT_EVENT_QUOTA_MONTHLY])]
    private int $eventQuotaMonthly = self::DEFAULT_EVENT_QUOTA_MONTHLY;

    #[ORM\Column(options: ['default' => self::DEFAULT_DELIVERY_HISTORY_LIMIT])]
    private int $notificationDeliveryHistoryLimit = self::DEFAULT_DELIVERY_HISTORY_LIMIT;

    #[ORM\Column(options: ['default' => self::DEFAULT_CIRCUIT_BREAKER_THRESHOLD])]
    private int $notificationCircuitBreakerThreshold = self::DEFAULT_CIRCUIT_BREAKER_THRESHOLD;

    #[ORM\Column(options: ['default' => self::DEFAULT_CIRCUIT_BREAKER_COOLDOWN_MINUTES])]
    private int $notificationCircuitBreakerCooldownMinutes = self::DEFAULT_CIRCUIT_BREAKER_COOLDOWN_MINUTES;

    #[ORM\Column(options: ['default' => self::DEFAULT_ENVELOPE_MAX_BYTES])]
    private int $envelopeMaxBytes = self::DEFAULT_ENVELOPE_MAX_BYTES;

    #[ORM\Column(options: ['default' => self::DEFAULT_INGEST_REJECT_QUERY_AUTH])]
    private bool $ingestRejectQueryAuth = self::DEFAULT_INGEST_REJECT_QUERY_AUTH;

    /** Prometheus scrape Bearer token (encrypted at rest). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $metricsToken = null;

    #[ORM\Column(options: ['default' => self::DEFAULT_METRICS_REQUIRE_TOKEN])]
    private bool $metricsRequireToken = self::DEFAULT_METRICS_REQUIRE_TOKEN;

    #[ORM\Column(options: ['default' => self::DEFAULT_INBOUND_EMAIL_ENABLED])]
    private bool $inboundEmailEnabled = self::DEFAULT_INBOUND_EMAIL_ENABLED;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $inboundMailDomain = null;

    /** Inbound webhook + Reply-To signing secret (encrypted at rest). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $inboundWebhookSecret = null;

    #[ORM\Column(options: ['default' => self::DEFAULT_ALLOW_PRIVATE_URLS])]
    private bool $allowPrivateUrls = self::DEFAULT_ALLOW_PRIVATE_URLS;

    #[ORM\Column(options: ['default' => self::DEFAULT_ALLOW_ANONYMOUS_RESOLVE])]
    private bool $allowAnonymousResolve = self::DEFAULT_ALLOW_ANONYMOUS_RESOLVE;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public static function defaults(): self
    {
        return new self();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMailerDsn(): ?string
    {
        return $this->mailerDsn;
    }

    public function setMailerDsn(?string $mailerDsn): self
    {
        if (null === $mailerDsn) {
            $this->mailerDsn = null;

            return $this;
        }

        $trimmed = trim($mailerDsn);
        $this->mailerDsn = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function hasMailerDsn(): bool
    {
        return null !== $this->mailerDsn && '' !== $this->mailerDsn;
    }

    public function maskedMailerDsn(): ?string
    {
        if (!$this->hasMailerDsn()) {
            return null;
        }

        $value = (string) $this->mailerDsn;
        $schemePos = strpos($value, '://');
        if (false === $schemePos) {
            return str_repeat('•', min(12, \strlen($value)));
        }

        $scheme = substr($value, 0, $schemePos + 3);
        $rest = substr($value, $schemePos + 3);
        if ('' === $rest) {
            return $scheme.'••••';
        }

        if (\strlen($rest) <= 8) {
            return $scheme.str_repeat('•', \strlen($rest));
        }

        return $scheme.substr($rest, 0, 2).str_repeat('•', max(4, \strlen($rest) - 6)).substr($rest, -4);
    }

    public function getMailerFrom(): ?string
    {
        return $this->mailerFrom;
    }

    public function getEffectiveMailerFrom(): string
    {
        $from = null !== $this->mailerFrom ? trim($this->mailerFrom) : '';

        return '' !== $from ? $from : self::DEFAULT_MAILER_FROM;
    }

    public function setMailerFrom(?string $mailerFrom): self
    {
        if (null === $mailerFrom) {
            $this->mailerFrom = null;

            return $this;
        }

        $trimmed = trim($mailerFrom);
        $this->mailerFrom = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function getSetupCompletedAt(): ?DateTimeImmutable
    {
        return $this->setupCompletedAt;
    }

    public function isSetupCompleted(): bool
    {
        return $this->setupCompletedAt instanceof DateTimeImmutable;
    }

    public function markSetupCompleted(?DateTimeImmutable $at = null): self
    {
        $this->setupCompletedAt = $at ?? new DateTimeImmutable();

        return $this;
    }

    public function clearSetupCompleted(): self
    {
        $this->setupCompletedAt = null;

        return $this;
    }

    public function isMercureEnabled(): bool
    {
        return $this->mercureEnabled;
    }

    public function setMercureEnabled(bool $mercureEnabled): self
    {
        $this->mercureEnabled = $mercureEnabled;

        return $this;
    }

    public function getMercureUrl(): ?string
    {
        return $this->mercureUrl;
    }

    public function setMercureUrl(?string $mercureUrl): self
    {
        if (null === $mercureUrl) {
            $this->mercureUrl = null;

            return $this;
        }

        $trimmed = trim($mercureUrl);
        $this->mercureUrl = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function getMercurePublicUrl(): ?string
    {
        return $this->mercurePublicUrl;
    }

    public function setMercurePublicUrl(?string $mercurePublicUrl): self
    {
        if (null === $mercurePublicUrl) {
            $this->mercurePublicUrl = null;

            return $this;
        }

        $trimmed = trim($mercurePublicUrl);
        $this->mercurePublicUrl = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function getMercureJwtSecret(): ?string
    {
        return $this->mercureJwtSecret;
    }

    public function setMercureJwtSecret(?string $mercureJwtSecret): self
    {
        if (null === $mercureJwtSecret) {
            $this->mercureJwtSecret = null;

            return $this;
        }

        $trimmed = trim($mercureJwtSecret);
        $this->mercureJwtSecret = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function hasMercureJwtSecret(): bool
    {
        return null !== $this->mercureJwtSecret && '' !== $this->mercureJwtSecret;
    }

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

    public function isIngestRejectQueryAuth(): bool
    {
        return $this->ingestRejectQueryAuth;
    }

    public function setIngestRejectQueryAuth(bool $ingestRejectQueryAuth): self
    {
        $this->ingestRejectQueryAuth = $ingestRejectQueryAuth;

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

    public function getCreatedBy(): ?object
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?object $createdBy): void
    {
        $this->createdBy = $createdBy instanceof User ? $createdBy : null;
    }

    public function getUpdatedBy(): ?object
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?object $updatedBy): void
    {
        $this->updatedBy = $updatedBy instanceof User ? $updatedBy : null;
    }
}
