<?php

declare(strict_types=1);

namespace App\Issues\Entity;

use App\Identity\Entity\User;
use App\Issues\IssueHistoryKind;
use App\Issues\Repository\IssueHistoryEntryRepository;
use App\Shared\IssueStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Timeline entry for assignee or status changes on an issue.
 */
#[ORM\Entity(repositoryClass: IssueHistoryEntryRepository::class)]
#[ORM\Table(name: 'issue_history')]
#[ORM\Index(name: 'idx_issue_history_issue_created', columns: ['issue_id', 'created_at'])]
class IssueHistoryEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'historyEntries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Issue $issue = null;

    #[ORM\Column(length: 32, enumType: IssueHistoryKind::class)]
    private IssueHistoryKind $kind = IssueHistoryKind::AssigneeChanged;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $fromAssignee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $toAssignee = null;

    #[ORM\Column(length: 20, enumType: IssueStatus::class, nullable: true)]
    private ?IssueStatus $fromStatus = null;

    #[ORM\Column(length: 20, enumType: IssueStatus::class, nullable: true)]
    private ?IssueStatus $toStatus = null;

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

    public function getIssue(): ?Issue
    {
        return $this->issue;
    }

    public function setIssue(?Issue $issue): self
    {
        $this->issue = $issue;

        return $this;
    }

    public function getKind(): IssueHistoryKind
    {
        return $this->kind;
    }

    public function setKind(IssueHistoryKind $kind): self
    {
        $this->kind = $kind;

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

    public function getFromAssignee(): ?User
    {
        return $this->fromAssignee;
    }

    public function setFromAssignee(?User $fromAssignee): self
    {
        $this->fromAssignee = $fromAssignee;

        return $this;
    }

    public function getToAssignee(): ?User
    {
        return $this->toAssignee;
    }

    public function setToAssignee(?User $toAssignee): self
    {
        $this->toAssignee = $toAssignee;

        return $this;
    }

    public function getFromStatus(): ?IssueStatus
    {
        return $this->fromStatus;
    }

    public function setFromStatus(?IssueStatus $fromStatus): self
    {
        $this->fromStatus = $fromStatus;

        return $this;
    }

    public function getToStatus(): ?IssueStatus
    {
        return $this->toStatus;
    }

    public function setToStatus(?IssueStatus $toStatus): self
    {
        $this->toStatus = $toStatus;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
