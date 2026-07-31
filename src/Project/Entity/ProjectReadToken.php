<?php

declare(strict_types=1);

namespace App\Project\Entity;

use App\Identity\Entity\User;
use App\Project\Repository\ProjectReadTokenRepository;
use App\Shared\Doctrine\PublicUuidTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Project-scoped Bearer token for JSON read API (hashed at rest; distinct from ingest keys).
 */
#[ORM\Entity(repositoryClass: ProjectReadTokenRepository::class)]
#[ORM\Table(name: 'project_read_token')]
#[ORM\UniqueConstraint(name: 'uniq_project_read_token_uuid', columns: ['uuid'])]
#[ORM\UniqueConstraint(name: 'uniq_project_read_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_project_read_token_project', columns: ['project_id'])]
class ProjectReadToken
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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    #[ORM\Column(length: 120)]
    private string $label = '';

    /** Short public prefix (e.g. brt_ab12cd34) shown in Settings; raw token only once. */
    #[ORM\Column(length: 16)]
    private string $prefix = '';

    /** SHA-256 hex of the raw Bearer token. */
    #[ORM\Column(length: 64)]
    private string $tokenHash = '';

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->ensureUuid();
        $this->createdAt = new DateTimeImmutable();
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;

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

    public function isActive(): bool
    {
        return $this->active && !$this->revokedAt instanceof DateTimeImmutable;
    }

    public function revoke(?DateTimeImmutable $at = null): self
    {
        $this->active = false;
        $this->revokedAt = $at ?? new DateTimeImmutable();

        return $this;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(?DateTimeImmutable $at = null): self
    {
        $this->lastUsedAt = $at ?? new DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
