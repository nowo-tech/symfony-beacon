<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Service;

use App\Ingest\Service\EnvelopeAuthParser;
use App\Ingest\Service\IngestRateLimiter;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectApiKeyRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use App\Ops\Metrics\MetricsCollector;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared OTLP HTTP ingress gate: body limits, auth, project governance, rate limit, metrics.
 *
 * Controllers keep OpenAPI attributes and signal-specific mapping/dispatch only.
 */
final readonly class OtlpIngestGateway
{
    public function __construct(
        private EnvelopeAuthParser $authParser,
        private ProjectRepository $projectRepository,
        private ProjectApiKeyRepository $apiKeyRepository,
        private IngestRateLimiter $ingestRateLimiter,
        private ProjectGovernanceResolver $governanceResolver,
        private MetricsCollector $metricsCollector,
        private InstanceOpsDefaults $opsDefaults,
    ) {
    }

    /**
     * Validate request and authorize ingest for `{projectId}`.
     *
     * @return Response|array{project: Project, body: string} Error response, or accepted body + project
     */
    public function accept(int $projectId, Request $request): Response|array
    {
        $body = $request->getContent();
        if ('' === $body) {
            return $this->respond('Empty body', Response::HTTP_BAD_REQUEST);
        }

        if (\strlen($body) > $this->opsDefaults->envelopeMaxBytes()) {
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

        return [
            'project' => $project,
            'body' => $body,
        ];
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    public function respond(string $content, int $status, array $extraHeaders = []): Response
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
