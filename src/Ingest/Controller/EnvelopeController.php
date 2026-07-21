<?php

declare(strict_types=1);

namespace App\Ingest\Controller;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\Service\EnvelopeAuthParser;
use App\Ingest\Service\EnvelopeParser;
use App\Ingest\Service\IngestRateLimiter;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectApiKeyRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
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
{"dsn":"https://PUBLIC:SECRET@beacon.example/1"}
{"type":"event","length":120}
{"event_id":"a1b2c3d4e5f6478899aabbccddeeff00","message":"Something broke","level":"error","platform":"php","timestamp":1721491200.0}
ENVELOPE;

    private const string QUERY_AUTH_WARNING = '299 - "Query string beacon_key/beacon_secret is deprecated; use X-Beacon-Auth or envelope dsn"';

    public function __construct(
        private EnvelopeAuthParser $authParser,
        private EnvelopeParser $envelopeParser,
        private ProjectRepository $projectRepository,
        private ProjectApiKeyRepository $apiKeyRepository,
        private IngestRateLimiter $ingestRateLimiter,
        private ProjectGovernanceResolver $governanceResolver,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/{projectId}/envelope/', name: 'ingest_envelope', requirements: ['projectId' => '\d+'], methods: ['POST'])]
    #[OA\Post(path: '/api/{projectId}/envelope/', operationId: 'ingestEnvelope', description: <<<'MD'
Accepts an Envelope body (newline-separated JSON header, item header, and payload).

**Auth (preferred first):**
- `X-Beacon-Auth` header with `beacon_key` + **required** `beacon_secret`
- Envelope first-line JSON `"dsn": "https://public:secret@host/projectId"`
- **Deprecated:** query `beacon_key` + `beacon_secret` (leaks into logs/Referer; responses include `Warning` / `Deprecation`)

The public key is an opaque identifier and MUST belong to `{projectId}`. Secret is always required. On success the body is empty and processing is queued asynchronously (`ProcessEnvelopeMessage`).
MD, summary: 'Ingest a Beacon Envelope', security: [
        ['BeaconAuth' => []],
        ['BeaconKeyQuery' => [], 'BeaconSecretQuery' => []],
    ], tags: ['Ingest'])]
    #[OA\Parameter(
        name: 'projectId',
        description: 'Numeric project id from the Beacon DSN path (not the project UUID).',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1, minimum: 1),
    )]
    #[OA\Parameter(
        name: 'beacon_key',
        description: 'Deprecated: public key query parameter. Prefer X-Beacon-Auth.',
        in: 'query',
        required: false,
        deprecated: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'beacon_secret',
        description: 'Deprecated: secret key query parameter (leaks into access logs). Prefer X-Beacon-Auth. Always required with the public key.',
        in: 'query',
        required: false,
        deprecated: true,
        schema: new OA\Schema(type: 'string'),
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
        description: 'Missing authorization (no public key in header, query, or envelope DSN).',
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(type: 'string', example: 'missing authorization information'),
        ),
    )]
    #[OA\Response(
        response: 403,
        description: 'Unknown/inactive key, project mismatch, or missing/invalid secret when the API key requires one.',
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(type: 'string', example: 'forbidden'),
        ),
    )]
    #[OA\Response(
        response: 404,
        description: 'Project id does not exist.',
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(type: 'string', example: 'project not found'),
        ),
    )]
    #[OA\Response(
        response: 429,
        description: 'Per-project ingest rate limit exceeded (`BEACON_INGEST_RATE_LIMIT`).',
        headers: [
            new OA\Header(
                header: 'Retry-After',
                description: 'Seconds to wait before retrying (typically 60).',
                schema: new OA\Schema(type: 'integer', example: 60),
            ),
        ],
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(type: 'string', example: 'rate limit exceeded'),
        ),
    )]
    public function __invoke(int $projectId, Request $request): Response
    {
        $body = $request->getContent();
        if ('' === $body) {
            return new Response('Empty body', Response::HTTP_BAD_REQUEST);
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
        $usedQueryAuth = $this->authParser->queryContainsCredentials($queryString);
        if ($usedQueryAuth) {
            $this->logger->warning('Deprecated Envelope ingest auth via query string; prefer X-Beacon-Auth or envelope dsn.', [
                'project_id' => $projectId,
                'client_ip' => $request->getClientIp(),
            ]);
        }

        $auth = $this->authParser->parseFromRequest(
            $request->headers->get('X-Beacon-Auth'),
            $queryString,
            $envelopeDsn,
        );

        if (null === $auth['public_key']) {
            return $this->ingestResponse('missing authorization information', Response::HTTP_UNAUTHORIZED, $usedQueryAuth);
        }

        $apiKey = $this->apiKeyRepository->findActiveByPublicKey($auth['public_key']);
        if (!$apiKey instanceof ProjectApiKey || !$apiKey->getProject() instanceof Project || $apiKey->getProject()->getId() !== $projectId) {
            return $this->ingestResponse('forbidden', Response::HTTP_FORBIDDEN, $usedQueryAuth);
        }

        $project = $apiKey->getProject();

        $storedSecret = $apiKey->getSecretKey();
        $providedSecret = $auth['secret_key'];
        if (null === $storedSecret || '' === $storedSecret
            || null === $providedSecret || !hash_equals($storedSecret, $providedSecret)
        ) {
            return $this->ingestResponse('forbidden', Response::HTTP_FORBIDDEN, $usedQueryAuth);
        }

        // Validate parseability early (fail fast) without doing heavy work.
        try {
            $this->envelopeParser->parse($body);
        } catch (Throwable $e) {
            return $this->ingestResponse('invalid envelope: '.$e->getMessage(), Response::HTTP_BAD_REQUEST, $usedQueryAuth);
        }

        if (null === $this->projectRepository->find($projectId)) {
            return $this->ingestResponse('project not found', Response::HTTP_NOT_FOUND, $usedQueryAuth);
        }

        if (!$project->isIngestEnabled()) {
            return $this->ingestResponse('ingest disabled', Response::HTTP_FORBIDDEN, $usedQueryAuth);
        }

        if ($this->governanceResolver->isDailyQuotaExceeded($project)) {
            return $this->ingestResponse('daily event quota exceeded', Response::HTTP_TOO_MANY_REQUESTS, $usedQueryAuth, [
                'Retry-After' => '60',
            ]);
        }

        $rateLimit = $this->governanceResolver->effectiveIngestRateLimit($project);
        if (!$this->ingestRateLimiter->accept($projectId, $rateLimit)) {
            return $this->ingestResponse('rate limit exceeded', Response::HTTP_TOO_MANY_REQUESTS, $usedQueryAuth, [
                'Retry-After' => '60',
            ]);
        }

        $this->bus->dispatch(new ProcessEnvelopeMessage(
            $projectId,
            $body,
            new DateTimeImmutable()->format(DateTimeInterface::ATOM),
        ));

        return $this->ingestResponse('', Response::HTTP_OK, $usedQueryAuth);
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    private function ingestResponse(string $content, int $status, bool $usedQueryAuth, array $extraHeaders = []): Response
    {
        $headers = $extraHeaders;
        if ($usedQueryAuth) {
            $headers['Deprecation'] = 'true';
            $headers['Warning'] = self::QUERY_AUTH_WARNING;
        }

        return new Response($content, $status, $headers);
    }
}
