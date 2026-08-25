<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\Controller;

use App\Ingest\Controller\EnvelopeController;
use App\Ingest\Service\EnvelopeAuthParser;
use App\Ingest\Service\EnvelopeParser;
use App\Ingest\Service\IngestProjectAccessGate;
use App\Ingest\Service\IngestRateLimiter;
use App\Issues\Repository\EventRepository;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Ops\Metrics\MetricsCollector;
use App\Project\Repository\ProjectApiKeyRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use App\Ops\Messenger\MessengerQueueHealth;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Ingest\Service\EventQuotaUsageStore;

final class EnvelopeControllerTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testScrubEnvelopeCredentialsRemovesDsnFromHeaderLine(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(EnvelopeController::class, 'scrubEnvelopeCredentials');

        $body = "{\"dsn\":\"https://pub:sec@beacon.test/uuid\"}\n{\"type\":\"event\",\"length\":2}\n{}";
        $scrubbed = $method->invoke($controller, $body);
        self::assertStringNotContainsString('pub:sec', $scrubbed);
        self::assertStringNotContainsString('"dsn"', $scrubbed);
        self::assertStringContainsString("{\"type\":\"event\",\"length\":2}\n{}", $scrubbed);

        self::assertSame('not-json', $method->invoke($controller, 'not-json'));
        self::assertSame("{\"ok\":1}\nrest", $method->invoke($controller, "{\"ok\":1}\nrest"));
    }

    public function testRecordIngestMetricMapsRejectReasons(): void
    {
        $cache = new ArrayAdapter();
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(new RuntimeException('offline'));
        $collector = new MetricsCollector(
            $cache,
            new MessengerQueueHealth($em),
            $this->createStub(NotificationDestinationRepository::class),
        );
        $controller = $this->controller(metrics: $collector, ops: $this->opsDefaultsWith(static function (): void {}));
        $method = new ReflectionMethod(EnvelopeController::class, 'recordIngestMetric');

        $method->invoke($controller, Response::HTTP_OK, '');
        $method->invoke($controller, Response::HTTP_UNAUTHORIZED, 'unauthorized');
        $method->invoke($controller, Response::HTTP_FORBIDDEN, 'forbidden');
        $method->invoke($controller, Response::HTTP_TOO_MANY_REQUESTS, 'rate limit exceeded');
        $method->invoke($controller, Response::HTTP_TOO_MANY_REQUESTS, 'quota exceeded');
        $method->invoke($controller, Response::HTTP_BAD_REQUEST, 'invalid');
        $method->invoke($controller, Response::HTTP_INTERNAL_SERVER_ERROR, 'boom');

        $series = $collector->collect();
        $byName = [];
        foreach ($series as $metric) {
            $byName[$metric['name']] = $metric;
        }
        self::assertSame(1.0, $byName['beacon_ingest_ack_total']['samples'][0]['value']);
        $rejects = [];
        foreach ($byName['beacon_ingest_reject_total']['samples'] as $sample) {
            $rejects[$sample['labels']['reason']] = $sample['value'];
        }
        self::assertSame(1.0, $rejects['unauthorized']);
        self::assertSame(1.0, $rejects['forbidden']);
        self::assertSame(1.0, $rejects['rate_limit']);
        self::assertSame(1.0, $rejects['quota']);
        self::assertSame(1.0, $rejects['invalid']);
        self::assertSame(1.0, $rejects['other']);
    }

    public function testIngestResponseRecordsAndReturnsStatus(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(EnvelopeController::class, 'ingestResponse');
        $response = $method->invoke($controller, 'invalid envelope', Response::HTTP_BAD_REQUEST, ['X-Test' => '1']);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('invalid envelope', $response->getContent());
        self::assertSame('1', $response->headers->get('X-Test'));
    }

    private function controller(
        ?MetricsCollector $metrics = null,
        ?InstanceOpsDefaults $ops = null,
    ): EnvelopeController {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(new RuntimeException('offline'));
        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(0);
        $events->method('countReceivedSinceForProject')->willReturn(0);
        $ops ??= $this->opsDefaultsWith(static function (): void {});
        $metrics ??= new MetricsCollector(
            new ArrayAdapter(),
            new MessengerQueueHealth($em),
            $this->createStub(NotificationDestinationRepository::class),
        );

        return new EnvelopeController(
            new EnvelopeAuthParser(),
            new EnvelopeParser(),
            new IngestProjectAccessGate(
                $this->createStub(ProjectRepository::class),
                $this->createStub(ProjectApiKeyRepository::class),
                new ProjectGovernanceResolver($ops, new EventQuotaUsageStore($events, new ArrayAdapter())),
                new IngestRateLimiter(new ArrayAdapter()),
                $em,
            ),
            $this->createStub(MessageBusInterface::class),
            new NullLogger(),
            $metrics,
            $ops,
        );
    }
}
