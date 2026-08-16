<?php

declare(strict_types=1);

namespace App\Tests\Unit\Performance\Entity;

use App\Performance\Entity\PerfSpan;
use PHPUnit\Framework\TestCase;

final class PerfSpanExtraTest extends TestCase
{
    public function testPerfSpanStartsWithoutPersistedId(): void
    {
        $span = new PerfSpan();

        self::assertNull($span->getId());
        self::assertSame('', $span->getSpanId());
    }
}
