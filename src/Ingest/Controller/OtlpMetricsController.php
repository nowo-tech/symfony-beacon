<?php

declare(strict_types=1);

namespace App\Ingest\Controller;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\Service\OtlpIngestGateway;
use App\Ingest\Service\OtlpMetricsMapper;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OTLP HTTP JSON metrics ingest: maps failure-like data points to Beacon events via the Envelope worker.
 */
#[AsController]
final readonly class OtlpMetricsController
{
    public function __construct(
        private OtlpIngestGateway $otlpIngestGateway,
        private OtlpMetricsMapper $otlpMetricsMapper,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/{projectId}/otlp/v1/metrics', name: 'ingest_otlp_metrics', requirements: ['projectId' => '\d+'], methods: ['POST'])]
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
        description: 'Numeric project id from the Beacon DSN path.',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1, minimum: 1),
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
    #[OA\Response(response: 401, description: 'Missing authorization.')]
    #[OA\Response(response: 403, description: 'Forbidden / ingest disabled.')]
    #[OA\Response(response: 404, description: 'Project not found.')]
    #[OA\Response(response: 413, description: 'Body too large.')]
    #[OA\Response(response: 429, description: 'Rate limit or quota exceeded.')]
    public function __invoke(int $projectId, Request $request): Response
    {
        $accepted = $this->otlpIngestGateway->accept($projectId, $request);
        if ($accepted instanceof Response) {
            return $accepted;
        }

        try {
            $payloads = $this->otlpMetricsMapper->mapToEventPayloads($accepted['body']);
        } catch (InvalidArgumentException $e) {
            $this->logger->notice('OTLP metrics parse rejected.', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return $this->otlpIngestGateway->respond('invalid otlp payload', Response::HTTP_BAD_REQUEST);
        }

        if ([] === $payloads) {
            return $this->otlpIngestGateway->respond('', Response::HTTP_OK);
        }

        $envelope = $this->otlpMetricsMapper->toEnvelopeBody($payloads);
        $this->bus->dispatch(new ProcessEnvelopeMessage(
            $projectId,
            $envelope,
            new DateTimeImmutable()->format(DateTimeInterface::ATOM),
        ));

        return $this->otlpIngestGateway->respond('', Response::HTTP_OK);
    }
}
