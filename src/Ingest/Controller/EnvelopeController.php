<?php

declare(strict_types=1);

namespace App\Ingest\Controller;

use App\Ingest\IngestRouteRequirements;
use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\Service\EnvelopeAuthParser;
use App\Ingest\Service\EnvelopeParser;
use App\Ingest\Service\IngestProjectAccessGate;
use App\Ops\Metrics\MetricsCollector;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use DateTimeImmutable;
use DateTimeInterface;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Envelope ingest endpoint: authenticates, ACKs quickly, and dispatches async processing.
 */
#[AsController]
final readonly class EnvelopeController
{
    private const string ENVELOPE_EXAMPLE = <<<'ENVELOPE'
{"dsn":"https://PUBLIC:SECRET@beacon.example/019fea2d-507b-7890-8b33-ca488db6f696"}
{"type":"event","length":120}
{"event_id":"a1b2c3d4e5f6478899aabbccddeeff00","message":"Something broke","level":"error","platform":"php","timestamp":1721491200.0}
ENVELOPE;

    public function __construct(
        private EnvelopeAuthParser $authParser,
        private EnvelopeParser $envelopeParser,
        private IngestProjectAccessGate $projectAccessGate,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        private MetricsCollector $metricsCollector,
        private InstanceOpsDefaults $opsDefaults,
    ) {
    }

    #[Route('/api/{projectId}/envelope/', name: 'ingest_envelope', requirements: ['projectId' => IngestRouteRequirements::PROJECT_REF], methods: ['POST'])]
    #[OA\Post(path: '/api/{projectId}/envelope/', operationId: 'ingestEnvelope', description: <<<'MD'
Accepts an Envelope body (newline-separated JSON header, item header, and payload).

**Auth (preferred first):**
- `X-Beacon-Auth` header with `beacon_key` + **required** `beacon_secret`
- Envelope first-line JSON `"dsn": "https://public:secret@host/projectUuid"`

Query-string `beacon_key` / `beacon_secret` are **removed** (always **401**). Prefer header or envelope DSN.

The public key is an opaque identifier and MUST belong to `{projectId}` (project UUID; legacy numeric id still accepted). Secret is always required. On success the body is empty and processing is queued asynchronously (`ProcessEnvelopeMessage`).
MD, summary: 'Ingest a Beacon Envelope', security: [
        ['BeaconAuth' => []],
    ], tags: ['Ingest'])]
    #[OA\Parameter(
        name: 'projectId',
        description: 'Project public UUID from the Beacon DSN path (legacy numeric primary key still accepted).',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: '019fea2d-507b-7890-8b33-ca488db6f696'),
    )]
    #[OA\RequestBody(description: 'Raw Envelope bytes. Preferred Content-Type: `application/x-beacon-envelope` (also accepts `application/octet-stream`).', required: true, content: [
        new OA\MediaType(
            mediaType: 'application/x-beacon-envelope',
            schema: new OA\Schema(
                description: 'Newline-delimited Envelope (header JSON, item header JSON, payload).',
                type: 'string',
                format: 'binary',
            ),
            example: self::ENVELOPE_EXAMPLE,
        ),
        new OA\MediaType(
            mediaType: 'application/octet-stream',
            schema: new OA\Schema(type: 'string', format: 'binary'),
            example: self::ENVELOPE_EXAMPLE,
        ),
    ])]
    #[OA\Response(
        response: 200,
        description: 'Envelope accepted; async processing queued. Empty body.',
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(type: 'string', example: '', maxLength: 0),
        ),
    )]
    #[OA\Response(
        response: 400,
        description: 'Empty body or Envelope failed early parse validation.',
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(type: 'string', example: 'invalid envelope: …'),
        ),
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized — missing credentials, query-string auth (removed), unknown project, inactive/mismatched key, or invalid secret (uniform body; no existence oracle).',
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(type: 'string', example: 'unauthorized'),
        ),
    )]
    #[OA\Response(
        response: 403,
        description: 'Authenticated but ingest is disabled for the project.',
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(type: 'string', example: 'ingest disabled'),
        ),
    )]
    #[OA\Response(
        response: 429,
        description: <<<'MD'
Too many requests — one of:

- **Rate limit** — per-project sliding window (instance ops default / project override); body `rate limit exceeded`; `Retry-After: 60`
- **Daily quota** — calendar-day event quota exceeded; body `daily event quota exceeded`; `Retry-After: 60`
- **Monthly quota** — UTC calendar-month event quota exceeded; body `monthly event quota exceeded`; `Retry-After: 3600`
MD,
        headers: [
            new OA\Header(
                header: 'Retry-After',
                description: 'Seconds to wait before retrying (60 for rate/daily; 3600 for monthly).',
                schema: new OA\Schema(type: 'integer', example: 60),
            ),
        ],
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(
                type: 'string',
                example: 'rate limit exceeded',
                enum: [
                    'rate limit exceeded',
                    'daily event quota exceeded',
                    'monthly event quota exceeded',
                ],
            ),
        ),
    )]
    public function __invoke(string $projectId, Request $request): Response
    {
        $body = $request->getContent();
        if ('' === $body) {
            return new Response('Empty body', Response::HTTP_BAD_REQUEST);
        }

        if (\strlen($body) > $this->opsDefaults->envelopeMaxBytes()) {
            return new Response('envelope too large', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $envelopeDsn = null;
        try {
            $headerLine = strtok(str_replace("\r\n", "\n", $body), "\n") ?: '';
            if ('' !== $headerLine) {
                $header = json_decode($headerLine, true);
                if (\is_array($header) && isset($header['dsn']) && \is_string($header['dsn'])) {
                    $envelopeDsn = $header['dsn'];
                }
            }
        } catch (Throwable) {
            // Auth may still succeed via HTTP header.
        }

        $queryString = $request->server->get('QUERY_STRING', '');
        if ($this->authParser->queryContainsCredentials($queryString)) {
            $this->logger->warning('Rejected Envelope ingest auth via query string; use X-Beacon-Auth or envelope dsn.', [
                'project_ref' => $projectId,
                'client_ip' => $request->getClientIp(),
            ]);

            return $this->ingestResponse(
                'query string authorization is not supported; use X-Beacon-Auth or envelope dsn',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $auth = $this->authParser->parseFromRequest(
            $request->headers->get('X-Beacon-Auth'),
            $envelopeDsn,
        );

        $access = $this->projectAccessGate->authorizeCredentials($projectId, $auth['public_key'], $auth['secret_key']);
        if (!$access['ok']) {
            return $this->ingestResponse($access['message'], $access['status'], $access['headers']);
        }

        $project = $access['project'];
        $numericProjectId = $access['project_id'];

        // Validate parseability early (fail fast) without doing heavy work.
        try {
            $this->envelopeParser->parse($body);
        } catch (Throwable $e) {
            $this->logger->notice('Envelope parse rejected.', [
                'project_id' => $numericProjectId,
                'error' => $e->getMessage(),
            ]);

            return $this->ingestResponse('invalid envelope', Response::HTTP_BAD_REQUEST);
        }

        $allowed = $this->projectAccessGate->assertIngestAllowed($project);
        if (!$allowed['ok']) {
            return $this->ingestResponse($allowed['message'], $allowed['status'], $allowed['headers']);
        }

        $this->bus->dispatch(new ProcessEnvelopeMessage(
            $numericProjectId,
            $this->scrubEnvelopeCredentials($body),
            new DateTimeImmutable()->format(DateTimeInterface::ATOM),
        ));

        return $this->ingestResponse('', Response::HTTP_OK);
    }

    /**
     * Remove DSN (may contain secret) from the envelope header before queue storage.
     */
    private function scrubEnvelopeCredentials(string $body): string
    {
        $normalized = str_replace("\r\n", "\n", $body);
        $pos = strpos($normalized, "\n");
        $headerLine = false === $pos ? $normalized : substr($normalized, 0, $pos);
        $rest = false === $pos ? '' : substr($normalized, $pos);

        try {
            $header = json_decode($headerLine, true, 512, \JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $body;
        }

        if (!\is_array($header) || !isset($header['dsn'])) {
            return $body;
        }

        unset($header['dsn']);
        $encoded = json_encode($header, \JSON_UNESCAPED_SLASHES);
        if (false === $encoded) {
            return $body;
        }

        return $encoded.$rest;
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    private function ingestResponse(string $content, int $status, array $extraHeaders = []): Response
    {
        $this->recordIngestMetric($status, $content);

        return new Response($content, $status, $extraHeaders);
    }

    private function recordIngestMetric(int $status, string $content): void
    {
        if (Response::HTTP_OK === $status) {
            $this->metricsCollector->recordIngestAck();

            return;
        }

        $reason = match (true) {
            Response::HTTP_UNAUTHORIZED === $status => 'unauthorized',
            Response::HTTP_FORBIDDEN === $status => 'forbidden',
            Response::HTTP_TOO_MANY_REQUESTS === $status && str_contains($content, 'rate limit') => 'rate_limit',
            Response::HTTP_TOO_MANY_REQUESTS === $status => 'quota',
            Response::HTTP_BAD_REQUEST === $status => 'invalid',
            default => 'other',
        };
        $this->metricsCollector->recordIngestReject($reason);
    }
}
