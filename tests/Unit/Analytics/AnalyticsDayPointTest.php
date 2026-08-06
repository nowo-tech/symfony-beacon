<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics;

use App\Analytics\Dto\AnalyticsDayPoint;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class AnalyticsDayPointTest extends TestCase
{
    public function testToChartArray(): void
    {
        $point = new AnalyticsDayPoint(
            new DateTimeImmutable('2026-08-06', new DateTimeZone('UTC')),
            12,
            3,
            null,
        );

        self::assertSame(
            [
                'date' => '2026-08-06',
                'errors' => 12,
                'transactions' => 3,
                'nplus1' => null,
            ],
            $point->toChartArray(),
        );
    }
}
