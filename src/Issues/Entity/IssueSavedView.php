<?php

declare(strict_types=1);

namespace App\Issues\Entity;

use App\Identity\Entity\User;
use App\Issues\Repository\IssueSavedViewRepository;
use App\Project\Entity\Project;
use App\Shared\Doctrine\PublicUuidTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Named per-user filter/sort snapshot for a project's issue list.
 */
#[ORM\Entity(repositoryClass: IssueSavedViewRepository::class)]
#[ORM\Table(name: 'issue_saved_view')]
#[ORM\UniqueConstraint(name: 'uniq_issue_saved_view_uuid', columns: ['uuid'])]
#[ORM\Index(name: 'idx_issue_saved_view_user_project', columns: ['user_id', 'project_id'])]
class IssueSavedView
{
    use PublicUuidTrait;

    public const int NAME_MAX_LENGTH = 80;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 80)]
    private string $name = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $queryJson = [];

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = mb_substr(trim($name), 0, self::NAME_MAX_LENGTH);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryJson(): array
    {
        return $this->queryJson;
    }

    /**
     * @param array<string, mixed> $queryJson
     */
    public function setQueryJson(array $queryJson): self
    {
        $this->queryJson = $queryJson;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
