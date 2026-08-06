<?php

declare(strict_types=1);

namespace App\Performance\Service;

use App\Performance\Entity\PerfTransaction;

/**
 * Outcome of persisting one Envelope transaction item (before Doctrine flush).
 */
final readonly class PerformanceEnvelopeWriteResult
{
    public function __construct(
        public PerfTransaction $transaction,
        public int $nPlusOneCount,
    ) {
    }
}
