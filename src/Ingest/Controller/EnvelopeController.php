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
            $request->headers->get('X-Sentry-Auth'),
            $request->server->get('QUERY_STRING', ''),
            $envelopeDsn,
        );

        if (null === $auth['sentry_key']) {
            return new Response('missing authorization information', Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = $this->apiKeyRepository->findActiveByPublicKey($auth['sentry_key']);
        if (!$apiKey instanceof ProjectApiKey || !$apiKey->getProject() instanceof Project || $apiKey->getProject()->getId() !== $projectId) {
            return new Response('forbidden', Response::HTTP_FORBIDDEN);
        }

        if (null !== $auth['sentry_secret'] && null !== $apiKey->getSecretKey() && !hash_equals($apiKey->getSecretKey(), $auth['sentry_secret'])) {
            return new Response('forbidden', Response::HTTP_FORBIDDEN);
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
