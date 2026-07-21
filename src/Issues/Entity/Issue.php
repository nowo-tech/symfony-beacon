<?php

declare(strict_types=1);

namespace App\Issues\Entity;

use App\Identity\Entity\User;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use App\Shared\Doctrine\PublicUuidTrait;
use App\Shared\IssueLevel;
use App\Shared\IssuePriority;
use App\Shared\IssueStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Grouped error issue keyed by project fingerprint, with optional assignee.
 */
#[ORM\Entity(repositoryClass: IssueRepository::class)]
#[ORM\Table(name: 'issue')]
#[ORM\UniqueConstraint(name: 'uniq_issue_uuid', columns: ['uuid'])]
#[ORM\UniqueConstraint(name: 'uniq_project_fingerprint', columns: ['project_id', 'fingerprint'])]
#[ORM\Index(name: 'idx_issue_project_last_seen', columns: ['project_id', 'last_seen'])]
#[ORM\Index(name: 'idx_issue_project_status', columns: ['project_id', 'status'])]
#[ORM\Index(name: 'idx_issue_project_assignee', columns: ['project_id', 'assignee_id'])]
#[ORM\Index(name: 'idx_issue_project_last_release', columns: ['project_id', 'last_release'])]
#[ORM\Index(name: 'idx_issue_project_priority', columns: ['project_id', 'priority'])]
#[ORM\Index(name: 'idx_issue_title_culprit_ft', columns: ['title', 'culprit'], flags: ['fulltext'])]
class Issue
{
    use PublicUuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignee = null;

    #[ORM\Column(length: 64)]
    private string $fingerprint = '';

    #[ORM\Column(length: 500)]
    private string $title = '';

    #[ORM\Column(length: 40)]
    private string $culprit = '';

    #[ORM\Column(length: 20, enumType: IssueLevel::class)]
    private IssueLevel $level = IssueLevel::Error;

    #[ORM\Column(length: 20, enumType: IssueStatus::class)]
    private IssueStatus $status = IssueStatus::Unresolved;

    #[ORM\Column(length: 20, enumType: IssuePriority::class)]
    private IssuePriority $priority = IssuePriority::Medium;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'duplicate_of_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Issue $duplicateOf = null;

    #[ORM\Column]
    private int $eventCount = 0;

    #[ORM\Column]
    private DateTimeImmutable $firstSeen;

    #[ORM\Column]
    private DateTimeImmutable $lastSeen;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $firstRelease = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $lastRelease = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $lastEnvironment = null;

    /** @var Collection<int, Event> */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'issue', orphanRemoval: true)]
    private Collection $events;

    /** @var Collection<int, IssueHistoryEntry> */
    #[ORM\OneToMany(targetEntity: IssueHistoryEntry::class, mappedBy: 'issue', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $historyEntries;

    /** @var Collection<int, IssueComment> */
    #[ORM\OneToMany(targetEntity: IssueComment::class, mappedBy: 'issue', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $comments;

    public function __construct()
    {
        $this->ensureUuid();
        $now = new DateTimeImmutable();
        $this->firstSeen = $now;
        $this->lastSeen = $now;
        $this->events = new ArrayCollection();
        $this->historyEntries = new ArrayCollection();
        $this->comments = new ArrayCollection();
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

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): self
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(string $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = mb_substr($title, 0, 500);

        return $this;
    }

    public function getCulprit(): string
    {
        return $this->culprit;
    }

    public function setCulprit(string $culprit): self
    {
        $this->culprit = mb_substr($culprit, 0, 40);

        return $this;
    }

    /**
     * String value for Twig / exports (backed enum).
     */
    public function getLevel(): string
    {
        return $this->level->value;
    }

    public function getLevelEnum(): IssueLevel
    {
        return $this->level;
    }

    public function setLevel(IssueLevel|string $level): self
    {
        $this->level = $level instanceof IssueLevel ? $level : IssueLevel::normalize($level);

        return $this;
    }

    public function getStatus(): IssueStatus
    {
        return $this->status;
    }

    public function setStatus(IssueStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPriority(): IssuePriority
    {
        return $this->priority;
    }

    public function setPriority(IssuePriority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getDuplicateOf(): ?self
    {
        return $this->duplicateOf;
    }

    public function setDuplicateOf(?self $duplicateOf): self
    {
        $this->duplicateOf = $duplicateOf;

        return $this;
    }

    public function getEventCount(): int
    {
        return $this->eventCount;
    }

    public function incrementEventCount(): self
    {
        ++$this->eventCount;

        return $this;
    }

    public function setEventCount(int $eventCount): self
    {
        $this->eventCount = max(0, $eventCount);

        return $this;
    }

    public function getFirstSeen(): DateTimeImmutable
    {
        return $this->firstSeen;
    }

    public function setFirstSeen(DateTimeImmutable $firstSeen): self
    {
        $this->firstSeen = $firstSeen;

        return $this;
    }

    public function getLastSeen(): DateTimeImmutable
    {
        return $this->lastSeen;
    }

    public function setLastSeen(DateTimeImmutable $lastSeen): self
    {
        $this->lastSeen = $lastSeen;

        return $this;
    }

    public function getFirstRelease(): ?string
    {
        return $this->firstRelease;
    }

    public function setFirstRelease(?string $firstRelease): self
    {
        $this->firstRelease = self::normalizeRelease($firstRelease);

        return $this;
    }

    public function getLastRelease(): ?string
    {
        return $this->lastRelease;
    }

    public function setLastRelease(?string $lastRelease): self
    {
        $this->lastRelease = self::normalizeRelease($lastRelease);

        return $this;
    }

    public function getLastEnvironment(): ?string
    {
        return $this->lastEnvironment;
    }

    public function setLastEnvironment(?string $lastEnvironment): self
    {
        $this->lastEnvironment = self::normalizeEnvironment($lastEnvironment);

        return $this;
    }

    /**
     * Trim, empty → null, truncate to column length (120).
     */
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

    /**
     * Trim, empty → null, truncate to column length (80).
     */
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

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    /**
     * @return Collection<int, IssueHistoryEntry>
     */
    public function getHistoryEntries(): Collection
    {
        return $this->historyEntries;
    }

    public function addHistoryEntry(IssueHistoryEntry $entry): self
    {
        if (!$this->historyEntries->contains($entry)) {
            $this->historyEntries->add($entry);
            $entry->setIssue($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, IssueComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(IssueComment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setIssue($this);
        }

        return $this;
    }
}
