<?php

declare(strict_types=1);

namespace App\Ops\Service;

/**
 * Structured dogfood hints after a Beacon DSN probe (ACK ≠ Web Push).
 */
final readonly class BeaconDogfoodDiagnosticReport
{
    /**
     * @param list<string> $warnings Operator-facing warnings (empty when nothing noteworthy)
     * @param list<string> $notes    Informal status lines (issue uuid, counts, …)
     */
    public function __construct(
        public bool $projectFound,
        public ?string $projectName,
        public bool $vapidConfigured,
        public int $pushSubscriptionCount,
        public bool $priorIssueExisted,
        public ?int $priorEventCount,
        public bool $eventPersisted,
        public ?string $issueUuid,
        public ?int $issueEventCount,
        public array $warnings = [],
        public array $notes = [],
    ) {
    }
}
