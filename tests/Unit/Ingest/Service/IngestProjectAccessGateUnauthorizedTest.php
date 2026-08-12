<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\Service;

use App\Ingest\Service\IngestProjectAccessGate;
use App\Ingest\Service\IngestRateLimiter;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectApiKeyRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Credential-path cases for {@see IngestProjectAccessGate} without mocking final services.
 */
final class IngestProjectAccessGateUnauthorizedTest extends TestCase
{
    public function testCredentialFailuresShareUniformUnauthorized(): void
    {
        $known = new Project();
        new ReflectionProperty(Project::class, 'id')->setValue($known, 42);

        $other = new Project();
        new ReflectionProperty(Project::class, 'id')->setValue($other, 99);

        $apiKey = ProjectApiKey::generate($known, 'Test');
        // Bind secret for hash_equals path.
        $secret = $apiKey->getSecretKey();
        self::assertNotNull($secret);

        $wrongProjectKey = ProjectApiKey::generate($other, 'Other');

        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('findOneByIngestPath')->willReturnCallback(
            static fn (string $ref): ?Project => match ($ref) {
                'missing' => null,
                default => $known,
            },
        );

        $keys = $this->createMock(ProjectApiKeyRepository::class);
        $keys->method('findActiveByPublicKey')->willReturnCallback(
            static fn(string $publicKey): ?ProjectApiKey => match ($publicKey) {
                $apiKey->getPublicKey() => $apiKey,
                $wrongProjectKey->getPublicKey() => $wrongProjectKey,
                default => null,
            },
        );

        $gate = new IngestProjectAccessGate(
            $projects,
            $keys,
            $this->unusedGovernance(),
            new IngestRateLimiter(new ArrayAdapter()),
        );

        $cases = [
            $gate->authorizeCredentials('missing', 'pk', 'sk'),
            $gate->authorizeCredentials('42', null, null),
            $gate->authorizeCredentials('42', 'unknown', 'sk'),
            $gate->authorizeCredentials('42', $wrongProjectKey->getPublicKey(), (string) $wrongProjectKey->getSecretKey()),
            $gate->authorizeCredentials('42', $apiKey->getPublicKey(), 'wrong-secret'),
        ];

        foreach ($cases as $reject) {
            self::assertFalse($reject['ok']);
            self::assertSame(Response::HTTP_UNAUTHORIZED, $reject['status']);
            self::assertSame(IngestProjectAccessGate::UNAUTHORIZED_MESSAGE, $reject['message']);
        }

        $ok = $gate->authorizeCredentials('42', $apiKey->getPublicKey(), $secret);
        self::assertTrue($ok['ok']);
        self::assertSame(42, $ok['project_id']);
    }

    public function testIngestDisabledReturnsForbiddenAfterAuth(): void
    {
        $project = new Project();
        new ReflectionProperty(Project::class, 'id')->setValue($project, 7);
        $project->setIngestEnabled(false);

        $gate = new IngestProjectAccessGate(
            $this->createMock(ProjectRepository::class),
            $this->createMock(ProjectApiKeyRepository::class),
            $this->unusedGovernance(),
            new IngestRateLimiter(new ArrayAdapter()),
        );

        $reject = $gate->assertIngestAllowed($project);
        self::assertFalse($reject['ok']);
        self::assertSame(Response::HTTP_FORBIDDEN, $reject['status']);
        self::assertSame('ingest disabled', $reject['message']);
    }

    /**
     * Governance is unused for credential checks / disabled ingest; build via reflection.
     */
    private function unusedGovernance(): ProjectGovernanceResolver
    {
        return new ReflectionClass(ProjectGovernanceResolver::class)->newInstanceWithoutConstructor();
    }
}
