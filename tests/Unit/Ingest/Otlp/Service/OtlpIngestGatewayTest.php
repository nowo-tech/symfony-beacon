<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\Otlp\Service;

use App\Ingest\Otlp\Service\OtlpIngestGateway;
use App\Ingest\Service\EnvelopeAuthParser;
use App\Ingest\Service\IngestProjectAccessGate;
use App\Ingest\Service\IngestRateLimiter;
use App\Issues\Repository\EventRepository;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Ops\Metrics\MetricsCollector;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectApiKeyRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use App\Ops\Messenger\MessengerQueueHealth;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class OtlpIngestGatewayTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testRejectsEmptyTooLargeAndQueryAuth(): void
    {
        $gateway = $this->gateway();
        self::assertSame(Response::HTTP_BAD_REQUEST, $gateway->accept('p', Request::create('/'))->getStatusCode());

        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setEnvelopeMaxBytes(10);
        });
        $gateway = $this->gateway($ops);
        $big = Request::create('/', Request::METHOD_POST, server: [], content: str_repeat('x', 11));
        self::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $gateway->accept('p', $big)->getStatusCode());

        $withQuery = Request::create('/?beacon_key=abc', Request::METHOD_POST, content: 'body');
        self::assertSame(Response::HTTP_UNAUTHORIZED, $gateway->accept('p', $withQuery)->getStatusCode());
    }

    public function testAcceptsAuthorizedProjectAndRecordsAckOnOkRespond(): void
    {
        $project = new Project();
        new ReflectionProperty(Project::class, 'id')->setValue($project, 7);
        $project->setIngestEnabled(true);
        $key = ProjectApiKey::generate($project, publicKey: 'pk', secretKey: 'sk');

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneByIngestPath')->willReturn($project);
        $keys = $this->createStub(ProjectApiKeyRepository::class);
        $keys->method('findActiveByPublicKey')->willReturn($key);
        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(0);
        $events->method('countReceivedSinceForProject')->willReturn(0);
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setEnvelopeMaxBytes(1024);
            $settings->setEventQuotaDaily(0);
            $settings->setEventQuotaMonthly(0);
            $settings->setIngestRateLimit(0);
        });
        $em = $this->createStub(EntityManagerInterface::class);
        $gate = new IngestProjectAccessGate(
            $projects,
            $keys,
            new ProjectGovernanceResolver($events, $ops),
            new IngestRateLimiter(new ArrayAdapter()),
            $em,
        );

        $cache = new ArrayAdapter();
        $queueEm = $this->createStub(EntityManagerInterface::class);
        $queueEm->method('getConnection')->willThrowException(new RuntimeException('offline'));
        $metrics = new MetricsCollector(
            $cache,
            new MessengerQueueHealth($queueEm),
            $this->createStub(NotificationDestinationRepository::class),
        );
        $gateway = new OtlpIngestGateway(new EnvelopeAuthParser(), $gate, $metrics, $ops);

        $request = Request::create('/', Request::METHOD_POST, server: [], content: '{"ok":true}');
        $request->headers->set('X-Beacon-Auth', 'Beacon beacon_key=pk, beacon_secret=sk');
        $accepted = $gateway->accept('ref', $request);
        self::assertIsArray($accepted);
        self::assertSame($project, $accepted['project']);

        $ok = $gateway->respond('acked', Response::HTTP_OK);
        self::assertSame(200, $ok->getStatusCode());
        $denied = $gateway->respond('nope', Response::HTTP_FORBIDDEN);
        self::assertSame(403, $denied->getStatusCode());
        self::assertSame(500, $gateway->respond('boom', Response::HTTP_INTERNAL_SERVER_ERROR)->getStatusCode());
    }

    private function gateway(?object $ops = null): OtlpIngestGateway
    {
        $ops ??= $this->opsDefaultsWith(static function ($settings): void {
            $settings->setEnvelopeMaxBytes(1024);
        });
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneByIngestPath')->willReturn(null);
        $em = $this->createStub(EntityManagerInterface::class);
        $events = $this->createStub(EventRepository::class);
        $gate = new IngestProjectAccessGate(
            $projects,
            $this->createStub(ProjectApiKeyRepository::class),
            new ProjectGovernanceResolver($events, $ops),
            new IngestRateLimiter(new ArrayAdapter()),
            $em,
        );
        $queueEm = $this->createStub(EntityManagerInterface::class);
        $queueEm->method('getConnection')->willThrowException(new RuntimeException('offline'));
        $metrics = new MetricsCollector(
            new ArrayAdapter(),
            new MessengerQueueHealth($queueEm),
            $this->createStub(NotificationDestinationRepository::class),
        );

        return new OtlpIngestGateway(new EnvelopeAuthParser(), $gate, $metrics, $ops);
    }
}
