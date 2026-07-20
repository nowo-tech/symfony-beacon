<?php

declare(strict_types=1);

namespace App\Issues\Dto;

/**
 * Occurrence counters for an Issue (lifetime + rolling windows).
 */
final readonly class IssueOccurrenceStats
{
    public function __construct(
        public int $total,
        public int $last24h,
        public int $last7d,
        public int $last30d,
    ) {
    }

    /**
     * @return array{total: int, last24h: int, last7d: int, last30d: int}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'last24h' => $this->last24h,
            'last7d' => $this->last7d,
            'last30d' => $this->last30d,
        ];
    }
}
