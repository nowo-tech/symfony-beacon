<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use App\Shared\Settings\Service\InstanceOpsDefaults;

/**
 * Whether Envelope ingest rejects deprecated query-string auth.
 *
 * Optional constructor override lets tests replace the service without mutating process env
 * (FrankenPHP-safe) or writing to the database mid-request.
 */
final readonly class IngestQueryAuthSettings
{
    public function __construct(
        private InstanceOpsDefaults $opsDefaults,
        private ?bool $rejectQueryAuth = null,
    ) {
    }

    public function shouldRejectQueryAuth(): bool
    {
        return $this->rejectQueryAuth ?? $this->opsDefaults->ingestRejectQueryAuth();
    }
}
