<?php

declare(strict_types=1);

namespace App\Issues\Entity;

use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stored Envelope event (full payload plus promoted context columns).
 *
 * {@see Project} is denormalized from the parent issue so event_id uniqueness
 * and retention queries are scoped per tenant without always joining issue.
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\UniqueConstraint(name: 'uniq_project_event_id', columns: ['project_id', 'event_id'])]
#[ORM\Index(name: 'idx_event_issue_received', columns: ['issue_id', 'received_at'])]
#[ORM\Index(name: 'idx_event_issue_environment', columns: ['issue_id', 'environment'])]
#[ORM\Index(name: 'idx_event_issue_release', columns: ['issue_id', 'release_version'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Issue $issue = null;

    #[ORM\Column(length: 64)]
    private string $eventId = '';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $environment = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $releaseVersion = null;

    #[ORM\Column(length: 40)]
    private string $platform = 'php';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phpVersion = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $symfonyVersion = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $userIdentifier = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'datetime_immutable', precision: 6)]
    private DateTimeImmutable $eventTimestamp;

    #[ORM\Column(type: 'datetime_immutable', precision: 6)]
    private DateTimeImmutable $receivedAt;

    public function __construct()
    {
        $this->eventTimestamp = new DateTimeImmutable();
        $this->receivedAt = new DateTimeImmutable();
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
        if ($issue instanceof Issue) {
            $project = $issue->getProject();
            if ($project instanceof Project) {
                $this->project = $project;
            }
        }

        return $this;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function setEventId(string $eventId): self
    {
        $this->eventId = $eventId;

        return $this;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    public function getReleaseVersion(): ?string
    {
        return $this->releaseVersion;
    }

    public function setReleaseVersion(?string $releaseVersion): self
    {
        $this->releaseVersion = $releaseVersion;

        return $this;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function getPhpVersion(): ?string
    {
        return $this->phpVersion;
    }

    public function setPhpVersion(?string $phpVersion): self
    {
        $this->phpVersion = $phpVersion;

        return $this;
    }

    public function getSymfonyVersion(): ?string
    {
        return $this->symfonyVersion;
    }

    public function setSymfonyVersion(?string $symfonyVersion): self
    {
        $this->symfonyVersion = $symfonyVersion;

        return $this;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): self
    {
        $this->userIdentifier = $userIdentifier;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getEventTimestamp(): DateTimeImmutable
    {
        return $this->eventTimestamp;
    }

    public function setEventTimestamp(DateTimeImmutable $eventTimestamp): self
    {
        $this->eventTimestamp = $eventTimestamp;

        return $this;
    }

    public function getReceivedAt(): DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(DateTimeImmutable $receivedAt): self
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }
}
