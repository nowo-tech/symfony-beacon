<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\Otlp\Controller;

use App\Ingest\Otlp\Controller\OtlpLogsController;
use App\Ingest\Otlp\Controller\OtlpMetricsController;
use App\Ingest\Otlp\Controller\OtlpTracesController;
use App\Ingest\Otlp\Service\OtlpIngestGatewayInterface;
use App\Ingest\Otlp\Service\OtlpIngestPipeline;
use App\Ingest\Otlp\Service\OtlpLogsMapper;
use App\Ingest\Otlp\Service\OtlpMetricsMapper;
use App\Ingest\Otlp\Service\OtlpTracesMapper;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

final class OtlpControllersTest extends TestCase
{
    public function testControllersDelegateDeniedGatewayResponse(): void
    {
        $gateway = new class implements OtlpIngestGatewayInterface {
            public function accept(string $projectRef, Request $request): Response
            {
                return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
            }

            public function respond(string $content, int $status, array $extraHeaders = []): Response
            {
                return new Response($content, $status, $extraHeaders);
            }
        };
        $pipeline = new OtlpIngestPipeline(
            $gateway,
            $this->createStub(MessageBusInterface::class),
            new NullLogger(),
        );
        $request = Request::create('/api/p/otlp/v1/logs', Request::METHOD_POST, content: '{}');

        $logs = new OtlpLogsController($pipeline, new OtlpLogsMapper());
        $traces = new OtlpTracesController($pipeline, new OtlpTracesMapper());
        $metrics = new OtlpMetricsController($pipeline, new OtlpMetricsMapper());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $logs('project-ref', $request)->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $traces('project-ref', $request)->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $metrics('project-ref', $request)->getStatusCode());
    }

    public function testControllersAckEmptyMappedPayloadsWithoutDispatch(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 9);

        $gateway = new readonly class($project) implements OtlpIngestGatewayInterface {
            public function __construct(private Project $project)
            {
            }

            public function accept(string $projectRef, Request $request): array
            {
                return ['project' => $this->project, 'body' => '{"resourceLogs":[]}'];
            }

            public function respond(string $content, int $status, array $extraHeaders = []): Response
            {
                return new Response($content, $status, $extraHeaders);
            }
        };

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $pipeline = new OtlpIngestPipeline($gateway, $bus, new NullLogger());
        $request = Request::create('/api/p/otlp/v1/logs', Request::METHOD_POST, content: '{"resourceLogs":[]}');

        self::assertSame(Response::HTTP_OK, (new OtlpLogsController($pipeline, new OtlpLogsMapper()))('project-ref', $request)->getStatusCode());
        self::assertSame(Response::HTTP_OK, (new OtlpTracesController($pipeline, new OtlpTracesMapper()))('project-ref', $request)->getStatusCode());
        self::assertSame(Response::HTTP_OK, (new OtlpMetricsController($pipeline, new OtlpMetricsMapper()))('project-ref', $request)->getStatusCode());
    }
}
