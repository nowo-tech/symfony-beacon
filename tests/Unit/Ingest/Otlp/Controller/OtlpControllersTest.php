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
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

final class OtlpControllersTest extends TestCase
{
    public function testControllersDelegateDeniedGatewayResponse(): void
    {
        $gateway = new class implements OtlpIngestGatewayInterface {
            public function accept(string $projectRef, Request $request): Response|array
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
        $request = Request::create('/api/p/otlp/v1/logs', 'POST', content: '{}');

        $logs = new OtlpLogsController($pipeline, new OtlpLogsMapper());
        $traces = new OtlpTracesController($pipeline, new OtlpTracesMapper());
        $metrics = new OtlpMetricsController($pipeline, new OtlpMetricsMapper());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $logs('project-ref', $request)->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $traces('project-ref', $request)->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $metrics('project-ref', $request)->getStatusCode());
    }
}
