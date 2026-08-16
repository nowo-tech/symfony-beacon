<?php

declare(strict_types=1);

namespace App\Tests\Unit\Performance\Entity;

use App\Performance\Entity\PerfSpan;
use App\Performance\Entity\PerfTransaction;
use PHPUnit\Framework\TestCase;

final class PerfTransactionEntityTest extends TestCase
{
    public function testPayloadAndSpanCollectionAccessors(): void
    {
        $transaction = new PerfTransaction();
        $span = new PerfSpan();

        $transaction->setPayload(['trace' => 'abc'])->addSpan($span);

        self::assertSame(['trace' => 'abc'], $transaction->getPayload());
        self::assertCount(1, $transaction->getSpans());
    }
}
