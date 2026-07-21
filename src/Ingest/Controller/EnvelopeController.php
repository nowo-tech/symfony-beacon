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
use DateTimeImmutable;
use DateTimeInterface;
use OpenApi\Attributes as OA;
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

    public function __construct(
        private EnvelopeAuthParser $authParser,
        private EnvelopeParser $envelopeParser,
        private ProjectRepository $projectRepository,
        private ProjectApiKeyRepository $apiKeyRepository,
        private IngestRateLimiter $ingestRateLimiter,
        private MessageBusInterface $bus,
    ) {
    }

    #[Route('/api/{projectId}/envelope/', name: 'ingest_envelope', requirements: ['projectId' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        path: '/api/{projectId}/envelope/',
        operationId: 'ingestEnvelope',
        summary: 'Ingest a Beacon Envelope',
        description: <<<'MD'
Accepts an Envelope body (newline-separated JSON header, item header, and payload).

**Auth (one of):**
- `X-Beacon-Auth` header with `beacon_key` + `beacon_secret` (secret required when the API key has one)
- Query `beacon_key` + `beacon_secret`
- Envelope first-line JSON `"dsn": "https://public:secret@host/projectId"`

The public key MUST belong to `{projectId}`. On success the body is empty and processing is queued asynchronously (`ProcessEnvelopeMessage`).
MD,
        security: [
            ['BeaconAuth' => []],
            ['BeaconKeyQuery' => [], 'BeaconSecretQuery' => []],
        ],
        tags: ['Ingest'],
    )]
    #[OA\Parameter(
        name: 'projectId',
        description: 'Numeric project id from the Beacon DSN path (not the project UUID).',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', minimum: 1, example: 1),
    )]
    #[OA\Parameter(
        name: 'beacon_key',
        description: 'Optional alternative to X-Beacon-Auth: public key query parameter.',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'beacon_secret',
        description: 'Optional alternative to X-Beacon-Auth: secret key query parameter (required when the API key has a secret).',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Raw Envelope bytes. Preferred Content-Type: `application/x-beacon-envelope` (also accepts `application/octet-stream`).',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-beacon-envelope',
                schema: new OA\Schema(
                    type: 'string',
                    format: 'binary',
                    description: 'Newline-delimited Envelope (header JSON, item header JSON, payload).',
                ),
                example: self::ENVELOPE_EXAMPLE,
            ),
            new OA\MediaType(
                mediaType: 'application/octet-stream',
                schema: new OA\Schema(type: 'string', format: 'binary'),
                example: self::ENVELOPE_EXAMPLE,
            ),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Envelope accepted; async processing queued. Empty body.',
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(type: 'string', maxLength: 0, example: ''),
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

        $auth = $this->authParser->parseFromRequest(
            $request->headers->get('X-Beacon-Auth'),
            $request->server->get('QUERY_STRING', ''),
            $envelopeDsn,
        );

        if (null === $auth['public_key']) {
            return new Response('missing authorization information', Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = $this->apiKeyRepository->findActiveByPublicKey($auth['public_key']);
        if (!$apiKey instanceof ProjectApiKey || !$apiKey->getProject() instanceof Project || $apiKey->getProject()->getId() !== $projectId) {
            return new Response('forbidden', Response::HTTP_FORBIDDEN);
        }

        $storedSecret = $apiKey->getSecretKey();
        if (null !== $storedSecret && '' !== $storedSecret) {
            $providedSecret = $auth['secret_key'];
            if (null === $providedSecret || !hash_equals($storedSecret, $providedSecret)) {
                return new Response('forbidden', Response::HTTP_FORBIDDEN);
            }
        }

        // Validate parseability early (fail fast) without doing heavy work.
        try {
            $this->envelopeParser->parse($body);
        } catch (Throwable $e) {
            return new Response('invalid envelope: '.$e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if (null === $this->projectRepository->find($projectId)) {
            return new Response('project not found', Response::HTTP_NOT_FOUND);
        }

        if (!$this->ingestRateLimiter->accept($projectId)) {
            return new Response('rate limit exceeded', Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => '60',
            ]);
        }

        $this->bus->dispatch(new ProcessEnvelopeMessage(
            $projectId,
            $body,
            new DateTimeImmutable()->format(DateTimeInterface::ATOM),
        ));

        return new Response('', Response::HTTP_OK);
    }
}
