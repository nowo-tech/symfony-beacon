<?php

declare(strict_types=1);

namespace App\Performance\Entity;

use App\Performance\Repository\PerfSpanRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PerfSpanRepository::class)]
#[ORM\Table(name: 'perf_span')]
#[ORM\Index(name: 'idx_span_tx_op', columns: ['transaction_id', 'op'])]
class PerfSpan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'spans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PerfTransaction $transaction = null;

    #[ORM\Column(length: 32)]
    private string $spanId = '';

    #[ORM\Column(length: 80)]
    private string $op = '';

    #[ORM\Column(length: 500)]
    private string $description = '';

    #[ORM\Column]
    private float $durationMs = 0.0;

    #[ORM\Column]
    private bool $nPlusOneCandidate = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransaction(): ?PerfTransaction
    {
        return $this->transaction;
    }

    public function setTransaction(?PerfTransaction $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function setSpanId(string $spanId): self
    {
        $this->spanId = $spanId;

        return $this;
    }

    public function getOp(): string
    {
        return $this->op;
    }

    public function setOp(string $op): self
    {
        $this->op = mb_substr($op, 0, 80);

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = mb_substr($description, 0, 500);

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

    public function isNPlusOneCandidate(): bool
    {
        return $this->nPlusOneCandidate;
    }

    public function setNPlusOneCandidate(bool $nPlusOneCandidate): self
    {
        $this->nPlusOneCandidate = $nPlusOneCandidate;

        return $this;
    }
}
