<?php

declare(strict_types=1);

namespace App\Tests\Unit\Performance;

use App\Performance\Entity\PerfTransaction;
use App\Performance\Service\PerformanceEnvelopeWriteResult;
use PHPUnit\Framework\TestCase;

final class PerformanceEnvelopeWriteResultTest extends TestCase
{
    public function testExposesTransactionAndNPlusOneCount(): void
    {
        $transaction = new PerfTransaction();
        $result = new PerformanceEnvelopeWriteResult($transaction, 3);

        self::assertSame($transaction, $result->transaction);
        self::assertSame(3, $result->nPlusOneCount);
    }
}
