<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Whether Envelope ingest rejects deprecated query-string auth.
 *
 * Injectable so tests can replace the service without mutating process env (FrankenPHP-safe).
 */
final readonly class IngestQueryAuthSettings
{
    public function __construct(
        #[Autowire('%beacon.ingest_reject_query_auth%')]
        private bool $rejectQueryAuth = true,
    ) {
    }

    public function shouldRejectQueryAuth(): bool
    {
        return $this->rejectQueryAuth;
    }
}
