<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Controller;

use App\Ingest\IngestRouteRequirements;
use App\Ingest\Otlp\Service\OtlpIngestPipeline;
use App\Ingest\Otlp\Service\OtlpMetricsMapper;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OTLP HTTP JSON metrics ingest: maps failure-like data points to Beacon events via the Envelope worker.
 */
#[AsController]
final readonly class OtlpMetricsController
{
    public function __construct(
        private OtlpIngestPipeline $otlpIngestPipeline,
        private OtlpMetricsMapper $otlpMetricsMapper,
    ) {
    }

    #[Route('/api/{projectId}/otlp/v1/metrics', name: 'ingest_otlp_metrics', requirements: ['projectId' => IngestRouteRequirements::PROJECT_REF], methods: ['POST'])]
    #[OA\Post(
        path: '/api/{projectId}/otlp/v1/metrics',
        operationId: 'ingestOtlpMetrics',
        description: <<<'MD'
Accepts an OTLP **ExportMetricsServiceRequest** JSON body (`resourceMetrics` / `scopeMetrics` / `metrics`).

Maps **failure-like** metric data points (error attributes and/or error-ish metric names) into Beacon events and queues the same async Envelope worker.
At most **200** data points are accepted per request. gRPC, protobuf, and time-series storage / Performance dashboards are out of scope for v1.

**Auth (required):** `X-Beacon-Auth: Beacon beacon_key=…, beacon_secret=…` bound to `{projectId}`.
Query-string auth is **not** accepted on this endpoint.
MD,
        summary: 'Ingest OTLP metrics (HTTP JSON)',
        security: [['BeaconAuth' => []]],
        tags: ['Ingest'],
    )]
    #[OA\Parameter(
        name: 'projectId',
        description: 'Project public UUID from the Beacon DSN path (legacy numeric id still accepted).',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: '019fea2d-507b-7890-8b33-ca488db6f696'),
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(type: 'object'),
            example: [
                'resourceMetrics' => [[
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => 'demo']],
                            ['key' => 'deployment.environment', 'value' => ['stringValue' => 'prod']],
                        ],
                    ],
                    'scopeMetrics' => [[
                        'metrics' => [[
                            'name' => 'http.server.errors',
                            'sum' => [
                                'dataPoints' => [[
                                    'asInt' => '1',
                                    'timeUnixNano' => '1721491200000000000',
                                    'attributes' => [
                                        ['key' => 'error.type', 'value' => ['stringValue' => 'TimeoutException']],
                                    ],
                                ]],
                            ],
                        ]],
                    ]],
                ]],
            ],
        ),
    )]
    #[OA\Response(response: 200, description: 'Accepted; async processing queued. Empty body.')]
    #[OA\Response(response: 400, description: 'Invalid JSON.')]
    #[OA\Response(response: 401, description: 'Unauthorized (missing/invalid credentials or unknown project).')]
    #[OA\Response(response: 403, description: 'Ingest disabled for the project.')]
    #[OA\Response(response: 413, description: 'Body too large.')]
    #[OA\Response(response: 429, description: 'Rate limit or quota exceeded.')]
    public function __invoke(string $projectId, Request $request): Response
    {
        return $this->otlpIngestPipeline->ingest($projectId, $request, $this->otlpMetricsMapper, 'metrics');
    }
}
