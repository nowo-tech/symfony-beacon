<?php

declare(strict_types=1);

namespace App\Performance\Dto;

/**
 * Performance list filters.
 */
final readonly class PerformanceFilters
{
    public function __construct(
        public bool $nPlusOneOnly,
    ) {
    }

    public static function fromRequestQuery(bool $nPlusOneOnly): self
    {
        return new self(nPlusOneOnly: $nPlusOneOnly);
    }
}
