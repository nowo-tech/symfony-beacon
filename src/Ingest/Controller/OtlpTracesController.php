<?php

declare(strict_types=1);

namespace App\Ingest\Controller;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\Service\EnvelopeAuthParser;
use App\Ingest\Service\IngestRateLimiter;
use App\Ingest\Service\OtlpTracesMapper;
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
 * OTLP HTTP JSON traces ingest: maps ERROR spans to Beacon events via the Envelope worker.
 */
#[AsController]
final readonly class OtlpTracesController
{
    public function __construct(
        private EnvelopeAuthParser $authParser,
        private OtlpTracesMapper $otlpTracesMapper,
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

    #[Route('/api/{projectId}/otlp/v1/traces', name: 'ingest_otlp_traces', requirements: ['projectId' => '\d+'], methods: ['POST'])]
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
            $payloads = $this->otlpTracesMapper->mapToEventPayloads($body);
        } catch (InvalidArgumentException $e) {
            $this->logger->notice('OTLP traces parse rejected.', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return $this->respond('invalid otlp payload', Response::HTTP_BAD_REQUEST);
        }

        if ([] === $payloads) {
            return $this->respond('', Response::HTTP_OK);
        }

        $envelope = $this->otlpTracesMapper->toEnvelopeBody($payloads);
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
