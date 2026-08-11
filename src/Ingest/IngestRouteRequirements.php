<?php

declare(strict_types=1);

namespace App\Ingest;

use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Shared route requirement for Envelope / OTLP `{projectId}` path segments.
 *
 * Preferred: project public UUID. Legacy numeric primary keys remain accepted.
 */
final class IngestRouteRequirements
{
    public const string PROJECT_REF = Requirement::POSITIVE_INT.'|'.Requirement::UUID;

    private function __construct()
    {
    }
}
