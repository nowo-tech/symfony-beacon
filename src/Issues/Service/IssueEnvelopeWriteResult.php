<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Issues\Entity\Issue;

/**
 * Outcome of persisting one Envelope event item (before Doctrine flush).
 */
final readonly class IssueEnvelopeWriteResult
{
    public function __construct(
        public bool $skipped,
        public ?Issue $issue = null,
        public bool $isNew = false,
        public bool $isRegression = false,
        public ?string $environment = null,
        public ?string $release = null,
        public bool $countsTowardVolumeThreshold = false,
    ) {
    }

    public static function skipped(): self
    {
        return new self(skipped: true);
    }
}
