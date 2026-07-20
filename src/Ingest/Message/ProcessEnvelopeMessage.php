<?php

declare(strict_types=1);

namespace App\Ingest\Message;

/**
 * Messenger message carrying a raw Envelope body for asynchronous processing.
 */
final readonly class ProcessEnvelopeMessage
{
    public function __construct(
        public int $projectId,
        public string $rawEnvelope,
        public string $receivedAtIso,
    ) {
    }
}
