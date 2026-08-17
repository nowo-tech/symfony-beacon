<?php

declare(strict_types=1);

namespace App\Ops\Service;

/**
 * One suite probe outcome (sync Envelope ACK).
 */
final readonly class BeaconDogfoodProbeCaseResult
{
    public function __construct(
        public string $kind,
        public bool $accepted,
        public ?string $eventId,
        public ?int $httpStatus,
        public ?string $error = null,
    ) {
    }
}
