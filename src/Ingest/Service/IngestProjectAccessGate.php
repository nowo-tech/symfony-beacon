<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectApiKeyRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;

/**
 * Shared Envelope/OTLP credential + governance checks (path project, API key, secret, quotas, rate).
 *
 * Callers own body-size limits, auth parsing (query/DSN), parse-early, and HTTP response shaping.
 *
 * @phpstan-type Reject array{ok: false, status: int, message: string, headers: array<string, string>}
 * @phpstan-type Accept array{ok: true, project: Project, project_id: int}
 */
final readonly class IngestProjectAccessGate
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectApiKeyRepository $apiKeyRepository,
        private ProjectGovernanceResolver $governanceResolver,
        private IngestRateLimiter $ingestRateLimiter,
    ) {
    }

    /**
     * Resolve path project + active API key/secret (no governance / rate yet).
     *
     * @return Accept|Reject
     */
    public function authorizeCredentials(string $projectRef, ?string $publicKey, ?string $secretKey): array
    {
        $pathProject = $this->projectRepository->findOneByIngestPath($projectRef);
        if (!$pathProject instanceof Project || null === $pathProject->getId()) {
            return $this->reject(404, 'project not found');
        }
        $projectId = $pathProject->getId();

        if (null === $publicKey || '' === $publicKey) {
            return $this->reject(401, 'missing authorization information');
        }

        $apiKey = $this->apiKeyRepository->findActiveByPublicKey($publicKey);
        if (!$apiKey instanceof ProjectApiKey || !$apiKey->getProject() instanceof Project || $apiKey->getProject()->getId() !== $projectId) {
            return $this->reject(403, 'forbidden');
        }

        $project = $apiKey->getProject();
        $storedSecret = $apiKey->getSecretKey();
        if (null === $storedSecret || '' === $storedSecret
            || null === $secretKey || !hash_equals($storedSecret, $secretKey)
        ) {
            return $this->reject(403, 'forbidden');
        }

        return [
            'ok' => true,
            'project' => $project,
            'project_id' => $projectId,
        ];
    }

    /**
     * Ingest enabled + quotas + rate limit for an already-authenticated project.
     *
     * @return Accept|Reject
     */
    public function assertIngestAllowed(Project $project): array
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return $this->reject(404, 'project not found');
        }

        if (!$project->isIngestEnabled()) {
            return $this->reject(403, 'ingest disabled');
        }

        if ($this->governanceResolver->isDailyQuotaExceeded($project)) {
            return $this->reject(429, 'daily event quota exceeded', ['Retry-After' => '60']);
        }

        if ($this->governanceResolver->isMonthlyQuotaExceeded($project)) {
            return $this->reject(429, 'monthly event quota exceeded', ['Retry-After' => '3600']);
        }

        $rateLimit = $this->governanceResolver->effectiveIngestRateLimit($project);
        if (!$this->ingestRateLimiter->accept($projectId, $rateLimit)) {
            return $this->reject(429, 'rate limit exceeded', ['Retry-After' => '60']);
        }

        return [
            'ok' => true,
            'project' => $project,
            'project_id' => $projectId,
        ];
    }

    /**
     * Credentials + governance in one step (OTLP and other adapters without a mid-parse step).
     *
     * @return Accept|Reject
     */
    public function authorize(string $projectRef, ?string $publicKey, ?string $secretKey): array
    {
        $credentials = $this->authorizeCredentials($projectRef, $publicKey, $secretKey);
        if (!$credentials['ok']) {
            return $credentials;
        }

        return $this->assertIngestAllowed($credentials['project']);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return Reject
     */
    private function reject(int $status, string $message, array $headers = []): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'message' => $message,
            'headers' => $headers,
        ];
    }
}
