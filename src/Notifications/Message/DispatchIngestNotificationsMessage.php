<?php

declare(strict_types=1);

namespace App\Notifications\Message;

/**
 * Triggers outbound ingest notifications after Envelope persistence (async queue).
 *
 * @phpstan-type AlertIntent array{
 *     kind: 'new'|'regression'|'nplus1',
 *     issue_id?: int,
 *     transaction_id?: int
 * }
 * @phpstan-type VolumeContext array{0: ?string, 1: ?string}
 */
final readonly class DispatchIngestNotificationsMessage
{
    /**
     * @param list<AlertIntent>   $alerts
     * @param list<VolumeContext> $volumeThresholdContexts
     */
    public function __construct(
        public int $projectId,
        public array $alerts,
        public array $volumeThresholdContexts,
        public string $receivedAtIso,
    ) {
    }
}
