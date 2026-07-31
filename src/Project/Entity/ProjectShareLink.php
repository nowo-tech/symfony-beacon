<?php

declare(strict_types=1);

namespace App\Project\Entity;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Shared\Doctrine\PublicUuidTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Time-limited signed share link for read-only project or issue access.
 */
#[ORM\Entity(repositoryClass: ProjectShareLinkRepository::class)]
#[ORM\Table(name: 'project_share_link')]
#[ORM\UniqueConstraint(name: 'uniq_project_share_link_uuid', columns: ['uuid'])]
#[ORM\UniqueConstraint(name: 'uniq_project_share_link_token', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_project_share_link_project', columns: ['project_id'])]
class ProjectShareLink
{
    use PublicUuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Issue $issue = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    /** SHA-256 hex of the raw token (raw token shown once). */
    #[ORM\Column(length: 64)]
    private string $tokenHash = '';

    #[ORM\Column]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    /** Null = unlimited uses within lifetime (legacy / explicit unlimited). */
    #[ORM\Column(nullable: true)]
    private ?int $maxUses = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $useCount = 0;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->ensureUuid();
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->expiresAt = $now->modify('+7 days');
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

    public function getIssue(): ?Issue
    {
        return $this->issue;
    }

    public function setIssue(?Issue $issue): self
    {
        $this->issue = $issue;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(?DateTimeImmutable $at = null): self
    {
        $this->revokedAt = $at ?? new DateTimeImmutable();

        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof DateTimeImmutable;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $this->expiresAt <= $now;
    }

    public function isUsable(?DateTimeImmutable $now = null): bool
    {
        return !$this->isRevoked() && !$this->isExpired($now) && !$this->isExhausted();
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function setMaxUses(?int $maxUses): self
    {
        $this->maxUses = $maxUses;

        return $this;
    }

    public function getUseCount(): int
    {
        return $this->useCount;
    }

    public function isExhausted(): bool
    {
        return null !== $this->maxUses && $this->useCount >= $this->maxUses;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(?DateTimeImmutable $at = null): self
    {
        ++$this->useCount;
        $this->lastUsedAt = $at ?? new DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
