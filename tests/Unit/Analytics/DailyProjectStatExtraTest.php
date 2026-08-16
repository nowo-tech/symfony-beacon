<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics;

use App\Analytics\Entity\DailyProjectStat;
use PHPUnit\Framework\TestCase;

final class DailyProjectStatExtraTest extends TestCase
{
    public function testDailyProjectStatStartsWithoutPersistedId(): void
    {
        $stat = new DailyProjectStat();

        self::assertNull($stat->getId());
        self::assertSame(0, $stat->getErrorCount());
    }
}
