<?php

declare(strict_types=1);

namespace App\Analytics\Dto;

use DateTimeImmutable;

/**
 * One UTC calendar day of analytics series data for chart + table.
 */
final readonly class AnalyticsDayPoint
{
    public function __construct(
        public DateTimeImmutable $date,
        public int $errorCount,
        public ?int $transactionCount,
        public ?int $nPlusOneCount,
    ) {
    }

    /**
     * @return array{date: string, errors: int, transactions: int|null, nplus1: int|null}
     */
    public function toChartArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'errors' => $this->errorCount,
            'transactions' => $this->transactionCount,
            'nplus1' => $this->nPlusOneCount,
        ];
    }
}
