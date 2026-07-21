<?php

declare(strict_types=1);

namespace App\Notifications\Entity;

use App\Notifications\Repository\ProjectThresholdRuleRepository;
use App\Project\Entity\Project;
use App\Shared\Doctrine\PublicUuidTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\TimestampableTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Per-project rolling error volume threshold rule.
 */
#[ORM\Entity(repositoryClass: ProjectThresholdRuleRepository::class)]
#[ORM\Table(name: 'project_threshold_rule')]
#[ORM\UniqueConstraint(name: 'uniq_project_threshold_rule_uuid', columns: ['uuid'])]
#[ORM\Index(name: 'idx_project_threshold_rule_project_enabled', columns: ['project_id', 'enabled'])]
class ProjectThresholdRule
{
    use PublicUuidTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'thresholdRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120, maxMessage: 'thresholds.validation.label_length')]
    private ?string $label = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => 50])]
    #[Assert\Range(notInRangeMessage: 'thresholds.validation.error_count_min', min: 1, max: 1000000)]
    private int $errorCount = 50;

    #[ORM\Column(options: ['default' => 15])]
    #[Assert\Range(notInRangeMessage: 'thresholds.validation.window_range', min: 1, max: 1440)]
    private int $windowMinutes = 15;

    #[ORM\Column(options: ['default' => 60])]
    #[Assert\Range(notInRangeMessage: 'thresholds.validation.cooldown_range', min: 1, max: 10080)]
    private int $cooldownMinutes = 60;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80, maxMessage: 'thresholds.validation.environment_length')]
    private ?string $environment = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120, maxMessage: 'thresholds.validation.release_length')]
    private ?string $releaseVersion = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastFiredAt = null;

    public function __construct()
    {
        $this->ensureUuid();
        $now = new DateTimeImmutable();
        $this->setCreatedAt($now);
        $this->setUpdatedAt($now);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $this->normalizeLabel($label);
        $this->touch();

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->touch();

        return $this;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function setErrorCount(int $errorCount): self
    {
        $this->errorCount = max(1, $errorCount);
        $this->touch();

        return $this;
    }

    public function getWindowMinutes(): int
    {
        return $this->windowMinutes;
    }

    public function setWindowMinutes(int $windowMinutes): self
    {
        $this->windowMinutes = max(1, min(1440, $windowMinutes));
        $this->touch();

        return $this;
    }

    public function getCooldownMinutes(): int
    {
        return $this->cooldownMinutes;
    }

    public function setCooldownMinutes(int $cooldownMinutes): self
    {
        $this->cooldownMinutes = max(1, min(10080, $cooldownMinutes));
        $this->touch();

        return $this;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): self
    {
        $this->environment = self::normalizeEnvironment($environment);
        $this->touch();

        return $this;
    }

    public function getReleaseVersion(): ?string
    {
        return $this->releaseVersion;
    }

    public function setReleaseVersion(?string $releaseVersion): self
    {
        $this->releaseVersion = self::normalizeRelease($releaseVersion);
        $this->touch();

        return $this;
    }

    public function getLastFiredAt(): ?DateTimeImmutable
    {
        return $this->lastFiredAt;
    }

    public function setLastFiredAt(?DateTimeImmutable $lastFiredAt): self
    {
        $this->lastFiredAt = $lastFiredAt;
        $this->touch();

        return $this;
    }

    public function markFired(?DateTimeImmutable $firedAt = null): self
    {
        $this->lastFiredAt = $firedAt ?? new DateTimeImmutable();
        $this->touch();

        return $this;
    }

    public function isCooldownActive(DateTimeImmutable $now): bool
    {
        if (!$this->lastFiredAt instanceof DateTimeImmutable) {
            return false;
        }

        return $this->lastFiredAt->modify(\sprintf('+%d minutes', $this->cooldownMinutes)) > $now;
    }

    public static function normalizeEnvironment(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        return mb_substr($trimmed, 0, 80);
    }

    public static function normalizeRelease(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        return mb_substr($trimmed, 0, 120);
    }

    private function normalizeLabel(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        return mb_substr($trimmed, 0, 120);
    }

    private function touch(): void
    {
        $this->setUpdatedAt(new DateTimeImmutable());
    }
}
