<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\UserActionRepository;
use App\Identity\UserActionType;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Immutable record of an administrative, membership, or product action involving users.
 *
 * Rows are append-only: there are no setters for {@see $createdAt}. Treat actor IP and
 * context (emails, roles, names, project/issue titles) as personal data under the
 * operator privacy policy.
 */
#[ORM\Entity(repositoryClass: UserActionRepository::class)]
#[ORM\Table(name: 'user_action')]
#[ORM\Index(name: 'idx_user_action_subject_created', columns: ['subject_user_id', 'created_at'])]
#[ORM\Index(name: 'idx_user_action_actor_created', columns: ['actor_id', 'created_at'])]
#[ORM\Index(name: 'idx_user_action_created', columns: ['created_at'])]
class UserAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 48, enumType: UserActionType::class)]
    private UserActionType $action = UserActionType::UserCreated;

    /** Authenticated user who performed the action, if any. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    /** User the action is primarily about (created, enabled, role-changed, …). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $subjectUser = null;

    /**
     * Structured details for the UI (project/group names, role from/to, email, …).
     *
     * @var array<string, scalar|null>
     */
    #[ORM\Column(type: 'json')]
    private array $context = [];

    /** Client IP captured from the request that triggered the action, when available. */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): UserActionType
    {
        return $this->action;
    }

    public function setAction(UserActionType $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function getSubjectUser(): ?User
    {
        return $this->subjectUser;
    }

    public function setSubjectUser(?User $subjectUser): self
    {
        $this->subjectUser = $subjectUser;

        return $this;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
