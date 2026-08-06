<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues;

use App\Issues\Dto\IssueOccurrenceStats;
use App\Issues\Service\IssueEnvelopeWriteResult;
use PHPUnit\Framework\TestCase;

final class IssueDtoAndWriteResultTest extends TestCase
{
    public function testOccurrenceStatsToArray(): void
    {
        $stats = new IssueOccurrenceStats(10, 2, 4, 8);

        self::assertSame(
            [
                'total' => 10,
                'last24h' => 2,
                'last7d' => 4,
                'last30d' => 8,
            ],
            $stats->toArray(),
        );
    }

    public function testSkippedWriteResultFactory(): void
    {
        $result = IssueEnvelopeWriteResult::skipped();

        self::assertTrue($result->skipped);
        self::assertNull($result->issue);
        self::assertFalse($result->isNew);
        self::assertFalse($result->isRegression);
        self::assertFalse($result->countsTowardVolumeThreshold);
    }
}
