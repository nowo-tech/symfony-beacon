<?php

declare(strict_types=1);

namespace App\Ingest\Controller;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\Service\EnvelopeAuthParser;
use App\Ingest\Service\IngestRateLimiter;
use App\Ingest\Service\OtlpMetricsMapper;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectApiKeyRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use App\Shared\Metrics\MetricsCollector;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private EnvelopeAuthParser $authParser,
        private OtlpMetricsMapper $otlpMetricsMapper,
        private ProjectRepository $projectRepository,
        private ProjectApiKeyRepository $apiKeyRepository,
        private IngestRateLimiter $ingestRateLimiter,
        private ProjectGovernanceResolver $governanceResolver,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        private MetricsCollector $metricsCollector,
        #[Autowire('%beacon.envelope_max_bytes%')]
        private int $maxBodyBytes = 2_097_152,
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
        $body = $request->getContent();
        if ('' === $body) {
            return $this->respond('Empty body', Response::HTTP_BAD_REQUEST);
        }

        if (\strlen($body) > $this->maxBodyBytes) {
            return $this->respond('otlp body too large', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if ($this->authParser->queryContainsCredentials($request->server->get('QUERY_STRING', ''))) {
            return $this->respond(
                'query string authorization is not supported for OTLP; use X-Beacon-Auth',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $auth = $this->authParser->parseFromRequest(
            $request->headers->get('X-Beacon-Auth'),
            '',
        );

        if (null === $auth['public_key']) {
            return $this->respond('missing authorization information', Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = $this->apiKeyRepository->findActiveByPublicKey($auth['public_key']);
        if (!$apiKey instanceof ProjectApiKey || !$apiKey->getProject() instanceof Project || $apiKey->getProject()->getId() !== $projectId) {
            return $this->respond('forbidden', Response::HTTP_FORBIDDEN);
        }

        $project = $apiKey->getProject();
        $storedSecret = $apiKey->getSecretKey();
        $providedSecret = $auth['secret_key'];
        if (null === $storedSecret || '' === $storedSecret
            || null === $providedSecret || !hash_equals($storedSecret, $providedSecret)
        ) {
            return $this->respond('forbidden', Response::HTTP_FORBIDDEN);
        }

        if (null === $this->projectRepository->find($projectId)) {
            return $this->respond('project not found', Response::HTTP_NOT_FOUND);
        }

        if (!$project->isIngestEnabled()) {
            return $this->respond('ingest disabled', Response::HTTP_FORBIDDEN);
        }

        if ($this->governanceResolver->isDailyQuotaExceeded($project)) {
            return $this->respond('daily event quota exceeded', Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => '60',
            ]);
        }

        if ($this->governanceResolver->isMonthlyQuotaExceeded($project)) {
            return $this->respond('monthly event quota exceeded', Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => '3600',
            ]);
        }

        $rateLimit = $this->governanceResolver->effectiveIngestRateLimit($project);
        if (!$this->ingestRateLimiter->accept($projectId, $rateLimit)) {
            return $this->respond('rate limit exceeded', Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => '60',
            ]);
        }

        try {
            $payloads = $this->otlpMetricsMapper->mapToEventPayloads($body);
        } catch (InvalidArgumentException $e) {
            $this->logger->notice('OTLP metrics parse rejected.', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return $this->respond('invalid otlp payload', Response::HTTP_BAD_REQUEST);
        }

        if ([] === $payloads) {
            return $this->respond('', Response::HTTP_OK);
        }

        $envelope = $this->otlpMetricsMapper->toEnvelopeBody($payloads);
        $this->bus->dispatch(new ProcessEnvelopeMessage(
            $projectId,
            $envelope,
            new DateTimeImmutable()->format(DateTimeInterface::ATOM),
        ));

        return $this->respond('', Response::HTTP_OK);
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    private function respond(string $content, int $status, array $extraHeaders = []): Response
    {
        if (Response::HTTP_OK === $status) {
            $this->metricsCollector->recordIngestAck();
        } else {
            $reason = match (true) {
                Response::HTTP_UNAUTHORIZED === $status => 'unauthorized',
                Response::HTTP_FORBIDDEN === $status => 'forbidden',
                Response::HTTP_TOO_MANY_REQUESTS === $status && str_contains($content, 'rate limit') => 'rate_limit',
                Response::HTTP_TOO_MANY_REQUESTS === $status => 'quota',
                Response::HTTP_BAD_REQUEST === $status => 'invalid',
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE === $status => 'too_large',
                default => 'other',
            };
            $this->metricsCollector->recordIngestReject($reason);
        }

        return new Response($content, $status, $extraHeaders);
    }
}
