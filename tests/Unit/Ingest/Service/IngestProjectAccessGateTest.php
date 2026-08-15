<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\Service;

use App\Ingest\Service\IngestProjectAccessGate;
use App\Ingest\Service\IngestRateLimiter;
use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectApiKeyRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class IngestProjectAccessGateTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    private ProjectRepository&Stub $projectRepository;
    private ProjectApiKeyRepository&Stub $apiKeyRepository;
    private EventRepository&Stub $eventRepository;
    private IngestRateLimiter $rateLimiter;
    private EntityManagerInterface&Stub $entityManager;
    private IngestProjectAccessGate $gate;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createStub(ProjectRepository::class);
        $this->apiKeyRepository = $this->createStub(ProjectApiKeyRepository::class);
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->rateLimiter = new IngestRateLimiter(new ArrayAdapter());
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->rebuild();
    }

    public function testAuthorizeCredentialsRejectsUnknownOrMismatchedSecretsUniformly(): void
    {
        $this->projectRepository->method('findOneByIngestPath')->willReturn(null);
        self::assertSame($this->unauthorized(), $this->gate->authorizeCredentials('missing', 'pk', 'sk'));

        $project = $this->project(10);
        $this->projectRepository = $this->createStub(ProjectRepository::class);
        $this->projectRepository->method('findOneByIngestPath')->willReturn($project);
        $this->apiKeyRepository = $this->createStub(ProjectApiKeyRepository::class);
        $this->apiKeyRepository->method('findActiveByPublicKey')->willReturn(null);
        $this->rebuild();

        self::assertSame($this->unauthorized(), $this->gate->authorizeCredentials('ref', null, 'sk'));
        self::assertSame($this->unauthorized(), $this->gate->authorizeCredentials('ref', 'pk', 'sk'));

        $other = $this->project(99);
        $key = ProjectApiKey::generate($other, secretKey: 'secret-value');
        $this->apiKeyRepository = $this->createStub(ProjectApiKeyRepository::class);
        $this->apiKeyRepository->method('findActiveByPublicKey')->willReturn($key);
        $this->rebuild();
        self::assertSame($this->unauthorized(), $this->gate->authorizeCredentials('ref', 'pk', 'secret-value'));

        $key = ProjectApiKey::generate($project, secretKey: 'secret-value');
        $this->apiKeyRepository = $this->createStub(ProjectApiKeyRepository::class);
        $this->apiKeyRepository->method('findActiveByPublicKey')->willReturn($key);
        $this->rebuild();
        self::assertSame($this->unauthorized(), $this->gate->authorizeCredentials('ref', 'pk', 'wrong'));
    }

    public function testAuthorizeCredentialsAcceptsMatchingKey(): void
    {
        $project = $this->project(10);
        $key = ProjectApiKey::generate($project, publicKey: 'public-key', secretKey: 'secret-value');
        $this->projectRepository->method('findOneByIngestPath')->willReturn($project);
        $this->apiKeyRepository->method('findActiveByPublicKey')->willReturn($key);

        self::assertSame(
            [
                'ok' => true,
                'project' => $project,
                'project_id' => 10,
            ],
            $this->gate->authorizeCredentials('ref', 'public-key', 'secret-value'),
        );
    }

    public function testAssertIngestAllowedEnforcesGovernanceAndRate(): void
    {
        $project = $this->project(10)->setIngestEnabled(false);
        self::assertSame(
            ['ok' => false, 'status' => 403, 'message' => 'ingest disabled', 'headers' => []],
            $this->gate->assertIngestAllowed($project),
        );

        $project->setIngestEnabled(true)->setEventQuotaDaily(1);
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->eventRepository->method('countReceivedTodayForProject')->willReturn(2);
        $this->rebuild();
        $daily = $this->gate->assertIngestAllowed($project);
        self::assertSame(429, $daily['status']);
        self::assertSame('daily event quota exceeded', $daily['message']);

        $project->setEventQuotaDaily(null)->setEventQuotaMonthly(1);
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->eventRepository->method('countReceivedTodayForProject')->willReturn(0);
        $this->eventRepository->method('countReceivedSinceForProject')->willReturn(5);
        $this->rebuild();
        self::assertSame('monthly event quota exceeded', $this->gate->assertIngestAllowed($project)['message']);

        $project->setEventQuotaMonthly(null)->setIngestRateLimitPerMinute(1);
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->eventRepository->method('countReceivedTodayForProject')->willReturn(0);
        $this->eventRepository->method('countReceivedSinceForProject')->willReturn(0);
        $this->rateLimiter = new IngestRateLimiter(new ArrayAdapter());
        $this->rebuild();
        $allowed = $this->gate->assertIngestAllowed($project);
        self::assertTrue($allowed['ok']);
        $limited = $this->gate->assertIngestAllowed($project);
        self::assertFalse($limited['ok']);
        self::assertSame('rate limit exceeded', $limited['message']);
    }

    public function testAuthorizeComposesCredentialsAndGovernance(): void
    {
        $project = $this->project(10)->setIngestEnabled(true);
        $key = ProjectApiKey::generate($project, publicKey: 'pk', secretKey: 'sk');
        $this->projectRepository->method('findOneByIngestPath')->willReturn($project);
        $this->apiKeyRepository->method('findActiveByPublicKey')->willReturn($key);
        $this->eventRepository->method('countReceivedTodayForProject')->willReturn(0);
        $this->eventRepository->method('countReceivedSinceForProject')->willReturn(0);

        $result = $this->gate->authorize('ref', 'pk', 'sk');
        self::assertTrue($result['ok']);
        self::assertSame(10, $result['project_id']);
    }

    private function rebuild(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setEventQuotaDaily(0);
            $settings->setEventQuotaMonthly(0);
            $settings->setIngestRateLimit(0);
        });

        $this->gate = new IngestProjectAccessGate(
            $this->projectRepository,
            $this->apiKeyRepository,
            new ProjectGovernanceResolver($this->eventRepository, $ops, new ArrayAdapter()),
            $this->rateLimiter,
            $this->entityManager,
        );
    }

    /** @return array{ok: false, status: int, message: string, headers: array<string, string>} */
    private function unauthorized(): array
    {
        return [
            'ok' => false,
            'status' => 401,
            'message' => IngestProjectAccessGate::UNAUTHORIZED_MESSAGE,
            'headers' => [],
        ];
    }

    private function project(int $id): Project
    {
        $project = new Project();
        new ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }
}
