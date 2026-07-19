<?php

declare(strict_types=1);

namespace App\Issues\Entity;

use App\Issues\Repository\EventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\UniqueConstraint(name: 'uniq_event_id', columns: ['event_id'])]
#[ORM\Index(name: 'idx_event_issue_received', columns: ['issue_id', 'received_at'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column]
    private \DateTimeImmutable $eventTimestamp;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    public function __construct()
    {
        $this->eventTimestamp = new \DateTimeImmutable();
        $this->receivedAt = new \DateTimeImmutable();
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

    public function getEventTimestamp(): \DateTimeImmutable
    {
        return $this->eventTimestamp;
    }

    public function setEventTimestamp(\DateTimeImmutable $eventTimestamp): self
    {
        $this->eventTimestamp = $eventTimestamp;

        return $this;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeImmutable $receivedAt): self
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }
}
