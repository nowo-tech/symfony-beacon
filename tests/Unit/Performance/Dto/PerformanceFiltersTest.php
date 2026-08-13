<?php

declare(strict_types=1);

namespace App\Tests\Unit\Performance\Dto;

use App\Performance\Dto\PerformanceFilters;
use PHPUnit\Framework\TestCase;

final class PerformanceFiltersTest extends TestCase
{
    public function testFromRequestQuery(): void
    {
        self::assertTrue(PerformanceFilters::fromRequestQuery(true)->nPlusOneOnly);
        self::assertFalse(PerformanceFilters::fromRequestQuery(false)->nPlusOneOnly);
    }
}
