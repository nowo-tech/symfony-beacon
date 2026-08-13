<?php

declare(strict_types=1);

namespace App\Shared\Settings\Entity;

use App\Identity\Entity\User;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\AuditableInterface;
use Nowo\AuditKitBundle\Model\TimestampableTrait;

/**
 * Singleton row for instance-wide operator settings (ROLE_ADMIN).
 */
#[ORM\Entity(repositoryClass: InstanceSettingsRepository::class)]
#[ORM\Table(name: 'instance_settings')]
class InstanceSettings implements AuditableInterface
{
    use InstanceSettingsMailerFields;
    use InstanceSettingsMercureFields;
    use InstanceSettingsOpsFields;
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
    public const DEFAULT_METRICS_REQUIRE_TOKEN = true;
    public const DEFAULT_INBOUND_EMAIL_ENABLED = false;
    public const DEFAULT_ALLOW_PRIVATE_URLS = false;
    public const DEFAULT_ALLOW_ANONYMOUS_RESOLVE = false;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    /** When set, the first-run setup wizard is considered finished / dismissed. */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $setupCompletedAt = null;

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
