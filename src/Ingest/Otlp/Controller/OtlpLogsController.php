<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Controller;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\Otlp\Service\OtlpIngestGateway;
use App\Ingest\Otlp\Service\OtlpLogsMapper;
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
 * OTLP HTTP JSON logs ingest: maps LogRecords to Beacon events via the Envelope worker.
 */
#[AsController]
final readonly class OtlpLogsController
{
    public function __construct(
        private OtlpIngestGateway $otlpIngestGateway,
        private OtlpLogsMapper $otlpLogsMapper,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/{projectId}/otlp/v1/logs', name: 'ingest_otlp_logs', requirements: ['projectId' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        path: '/api/{projectId}/otlp/v1/logs',
        operationId: 'ingestOtlpLogs',
        description: <<<'MD'
Accepts an OTLP **ExportLogsServiceRequest** JSON body (`resourceLogs` / `scopeLogs` / `logRecords`).

Maps WARN+ LogRecords (severityNumber ≥ 13) into Beacon events and queues the same async Envelope worker.
At most **200** records are accepted per request. Metrics and gRPC are out of scope for v1 (see `/otlp/v1/traces` for ERROR span ingest).

**Auth (required):** `X-Beacon-Auth: Beacon beacon_key=…, beacon_secret=…` bound to `{projectId}`.
Query-string auth is **not** accepted on this endpoint.
MD,
        summary: 'Ingest OTLP logs (HTTP JSON)',
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
                'resourceLogs' => [[
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => 'demo']],
                            ['key' => 'deployment.environment', 'value' => ['stringValue' => 'prod']],
                        ],
                    ],
                    'scopeLogs' => [[
                        'logRecords' => [[
                            'timeUnixNano' => '1721491200000000000',
                            'severityNumber' => 17,
                            'severityText' => 'ERROR',
                            'body' => ['stringValue' => 'Something broke'],
                        ]],
                    ]],
                ]],
            ],
        ),
    )]
    #[OA\Response(response: 200, description: 'Accepted; async processing queued. Empty body.')]
    #[OA\Response(response: 400, description: 'Invalid JSON or empty mapped set after filters.')]
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
            $payloads = $this->otlpLogsMapper->mapToEventPayloads($accepted['body']);
        } catch (InvalidArgumentException $e) {
            $this->logger->notice('OTLP logs parse rejected.', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return $this->otlpIngestGateway->respond('invalid otlp payload', Response::HTTP_BAD_REQUEST);
        }

        if ([] === $payloads) {
            // Valid OTLP with only dropped severities — ACK without queue work.
            return $this->otlpIngestGateway->respond('', Response::HTTP_OK);
        }

        $envelope = $this->otlpLogsMapper->toEnvelopeBody($payloads);
        $this->bus->dispatch(new ProcessEnvelopeMessage(
            $projectId,
            $envelope,
            new DateTimeImmutable()->format(DateTimeInterface::ATOM),
        ));

        return $this->otlpIngestGateway->respond('', Response::HTTP_OK);
    }
}
