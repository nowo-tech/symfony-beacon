<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Controller;

use App\Ingest\IngestRouteRequirements;
use App\Ingest\Otlp\Service\OtlpIngestPipeline;
use App\Ingest\Otlp\Service\OtlpTracesMapper;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OTLP HTTP JSON traces ingest: maps ERROR spans to Beacon events via the Envelope worker.
 */
#[AsController]
final readonly class OtlpTracesController
{
    public function __construct(
        private OtlpIngestPipeline $otlpIngestPipeline,
        private OtlpTracesMapper $otlpTracesMapper,
    ) {
    }

    #[Route('/api/{projectId}/otlp/v1/traces', name: 'ingest_otlp_traces', requirements: ['projectId' => IngestRouteRequirements::PROJECT_REF], methods: ['POST'])]
    #[OA\Post(
        path: '/api/{projectId}/otlp/v1/traces',
        operationId: 'ingestOtlpTraces',
        description: <<<'MD'
Accepts an OTLP **ExportTraceServiceRequest** JSON body (`resourceSpans` / `scopeSpans` / `spans`).

Maps **ERROR** spans (status code ERROR and/or exception attributes) into Beacon events and queues the same async Envelope worker.
At most **200** spans are accepted per request. Metrics, gRPC, protobuf, and full Performance waterfall ingest are out of scope for v1.

**Auth (required):** `X-Beacon-Auth: Beacon beacon_key=…, beacon_secret=…` bound to `{projectId}`.
Query-string auth is **not** accepted on this endpoint.
MD,
        summary: 'Ingest OTLP traces (HTTP JSON)',
        security: [['BeaconAuth' => []]],
        tags: ['Ingest'],
    )]
    #[OA\Parameter(
        name: 'projectId',
        description: 'Project public UUID from the Beacon DSN path.',
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
                'resourceSpans' => [[
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => 'demo']],
                            ['key' => 'deployment.environment', 'value' => ['stringValue' => 'prod']],
                        ],
                    ],
                    'scopeSpans' => [[
                        'spans' => [[
                            'traceId' => 'aabbccdd',
                            'spanId' => '11223344',
                            'name' => 'POST /checkout',
                            'endTimeUnixNano' => '1721491200000000000',
                            'status' => ['code' => 2, 'message' => 'payment failed'],
                            'attributes' => [
                                ['key' => 'exception.type', 'value' => ['stringValue' => 'RuntimeException']],
                                ['key' => 'exception.message', 'value' => ['stringValue' => 'payment failed']],
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
        return $this->otlpIngestPipeline->ingest($projectId, $request, $this->otlpTracesMapper, 'traces');
    }
}
