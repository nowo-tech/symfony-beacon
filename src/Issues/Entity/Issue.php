<?php

declare(strict_types=1);

namespace App\Issues\Entity;

use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use App\Shared\IssueStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IssueRepository::class)]
#[ORM\Table(name: 'issue')]
#[ORM\UniqueConstraint(name: 'uniq_project_fingerprint', columns: ['project_id', 'fingerprint'])]
#[ORM\Index(name: 'idx_issue_project_last_seen', columns: ['project_id', 'last_seen'])]
#[ORM\Index(name: 'idx_issue_project_status', columns: ['project_id', 'status'])]
class Issue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 64)]
    private string $fingerprint = '';

    #[ORM\Column(length: 500)]
    private string $title = '';

    #[ORM\Column(length: 40)]
    private string $culprit = '';

    #[ORM\Column(length: 20)]
    private string $level = 'error';

    #[ORM\Column(length: 20, enumType: IssueStatus::class)]
    private IssueStatus $status = IssueStatus::Unresolved;

    #[ORM\Column]
    private int $eventCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $firstSeen;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeen;

    /** @var Collection<int, Event> */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'issue', orphanRemoval: true)]
    private Collection $events;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->firstSeen = $now;
        $this->lastSeen = $now;
        $this->events = new ArrayCollection();
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

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): self
    {
        $this->level = $level;

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

    public function getEventCount(): int
    {
        return $this->eventCount;
    }

    public function incrementEventCount(): self
    {
        ++$this->eventCount;

        return $this;
    }

    public function getFirstSeen(): \DateTimeImmutable
    {
        return $this->firstSeen;
    }

    public function setFirstSeen(\DateTimeImmutable $firstSeen): self
    {
        $this->firstSeen = $firstSeen;

        return $this;
    }

    public function getLastSeen(): \DateTimeImmutable
    {
        return $this->lastSeen;
    }

    public function setLastSeen(\DateTimeImmutable $lastSeen): self
    {
        $this->lastSeen = $lastSeen;

        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }
}
