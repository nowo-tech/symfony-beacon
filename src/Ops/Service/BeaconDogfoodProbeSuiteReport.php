<?php

declare(strict_types=1);

namespace App\Ops\Service;

/**
 * Aggregate result of a dogfood probe suite run (or check-only preview).
 */
final readonly class BeaconDogfoodProbeSuiteReport
{
    /**
     * Preferred kinds for post-ACK dogfood diagnostics (new-issue path + rich UI).
     *
     * @var list<string>
     */
    private const DIAGNOSTIC_KIND_PREFERENCE = ['console', 'exception', 'http', 'messenger', 'message-error', 'breadcrumbs', 'message-info'];

    /**
     * @param array{
     *     origin: string,
     *     project_id: string,
     *     public_key: string,
     *     envelope_url: string,
     *     reporting_enabled: bool
     * }|array{} $target
     * @param list<BeaconDogfoodProbeCaseResult> $cases
     * @param list<string>                       $plannedKinds Kinds that would be sent (check-only)
     */
    public function __construct(
        public string $runToken,
        public array $target,
        public array $cases,
        public bool $success,
        public ?string $errorMessage = null,
        public array $plannedKinds = [],
    ) {
    }

    /**
     * Event id to feed into {@see BeaconDogfoodDiagnostics} (prefers console / exception).
     */
    public function diagnosticEventId(): ?string
    {
        $byKind = [];
        foreach ($this->cases as $case) {
            if ($case->accepted && null !== $case->eventId && '' !== $case->eventId) {
                $byKind[$case->kind] = $case->eventId;
            }
        }

        foreach (self::DIAGNOSTIC_KIND_PREFERENCE as $kind) {
            if (isset($byKind[$kind])) {
                return $byKind[$kind];
            }
        }

        return null;
    }
}
