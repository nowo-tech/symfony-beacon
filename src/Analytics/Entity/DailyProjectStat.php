<?php

declare(strict_types=1);

namespace App\Analytics\Entity;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyProjectStatRepository::class)]
#[ORM\Table(name: 'daily_project_stat')]
#[ORM\UniqueConstraint(name: 'uniq_project_stat_day', columns: ['project_id', 'stat_date'])]
class DailyProjectStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $statDate;

    #[ORM\Column]
    private int $errorCount = 0;

    #[ORM\Column]
    private int $transactionCount = 0;

    #[ORM\Column]
    private int $nPlusOneCount = 0;

    public function __construct()
    {
        $this->statDate = new DateTimeImmutable('today');
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

    public function getStatDate(): DateTimeImmutable
    {
        return $this->statDate;
    }

    public function setStatDate(DateTimeImmutable $statDate): self
    {
        $this->statDate = $statDate;

        return $this;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function incrementErrorCount(int $by = 1): self
    {
        $this->errorCount += $by;

        return $this;
    }

    public function getTransactionCount(): int
    {
        return $this->transactionCount;
    }

    public function incrementTransactionCount(int $by = 1): self
    {
        $this->transactionCount += $by;

        return $this;
    }

    public function getNPlusOneCount(): int
    {
        return $this->nPlusOneCount;
    }

    public function incrementNPlusOneCount(int $by = 1): self
    {
        $this->nPlusOneCount += $by;

        return $this;
    }
}
