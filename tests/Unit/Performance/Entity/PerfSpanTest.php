<?php

declare(strict_types=1);

namespace App\Tests\Unit\Performance\Entity;

use App\Performance\Entity\PerfSpan;
use App\Performance\Entity\PerfTransaction;
use PHPUnit\Framework\TestCase;

final class PerfSpanTest extends TestCase
{
    public function testNormalizesFieldLengthsAndFlags(): void
    {
        $transaction = new PerfTransaction();
        $span = (new PerfSpan())
            ->setTransaction($transaction)
            ->setSpanId('span-123')
            ->setOp(str_repeat('o', 100))
            ->setDescription(str_repeat('d', 700))
            ->setDurationMs(12.5)
            ->setNPlusOneCandidate(true);

        self::assertSame($transaction, $span->getTransaction());
        self::assertSame('span-123', $span->getSpanId());
        self::assertSame(80, \strlen($span->getOp()));
        self::assertSame(500, \strlen($span->getDescription()));
        self::assertSame(12.5, $span->getDurationMs());
        self::assertTrue($span->isNPlusOneCandidate());
    }
}
