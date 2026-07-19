<?php

declare(strict_types=1);

namespace App\Performance\Entity;

use App\Performance\Repository\PerfTransactionRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PerfTransactionRepository::class)]
#[ORM\Table(name: 'perf_transaction')]
#[ORM\Index(name: 'idx_tx_project_received', columns: ['project_id', 'received_at'])]
#[ORM\Index(name: 'idx_tx_nplus1', columns: ['project_id', 'n_plus_one_count'])]
class PerfTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 64)]
    private string $eventId = '';

    #[ORM\Column(length: 200)]
    private string $transactionName = '';

    #[ORM\Column]
    private float $durationMs = 0.0;

    #[ORM\Column]
    private int $spanCount = 0;

    #[ORM\Column]
    private int $nPlusOneCount = 0;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    /** @var Collection<int, PerfSpan> */
    #[ORM\OneToMany(targetEntity: PerfSpan::class, mappedBy: 'transaction', cascade: ['persist'], orphanRemoval: true)]
    private Collection $spans;

    #[ORM\Column]
    private DateTimeImmutable $receivedAt;

    public function __construct()
    {
        $this->spans = new ArrayCollection();
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

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function setEventId(string $eventId): self
    {
        $this->eventId = $eventId;

        return $this;
    }

    public function getTransactionName(): string
    {
        return $this->transactionName;
    }

    public function setTransactionName(string $transactionName): self
    {
        $this->transactionName = mb_substr($transactionName, 0, 200);

        return $this;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    public function setDurationMs(float $durationMs): self
    {
        $this->durationMs = $durationMs;

        return $this;
    }

    public function getSpanCount(): int
    {
        return $this->spanCount;
    }

    public function setSpanCount(int $spanCount): self
    {
        $this->spanCount = $spanCount;

        return $this;
    }

    public function getNPlusOneCount(): int
    {
        return $this->nPlusOneCount;
    }

    public function setNPlusOneCount(int $nPlusOneCount): self
    {
        $this->nPlusOneCount = $nPlusOneCount;

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

    /**
     * @return Collection<int, PerfSpan>
     */
    public function getSpans(): Collection
    {
        return $this->spans;
    }

    public function addSpan(PerfSpan $span): self
    {
        if (!$this->spans->contains($span)) {
            $this->spans->add($span);
            $span->setTransaction($this);
        }

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
