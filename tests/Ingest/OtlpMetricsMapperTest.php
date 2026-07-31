<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Ingest\Service\OtlpMetricsMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OtlpMetricsMapperTest extends TestCase
{
    public function testMapsFailureMetricAndDropsHealthy(): void
    {
        $mapper = new OtlpMetricsMapper();
        $json = json_encode([
            'resourceMetrics' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'billing']],
                        ['key' => 'deployment.environment', 'value' => ['stringValue' => 'prod']],
                        ['key' => 'service.version', 'value' => ['stringValue' => '2.1.0']],
                    ],
                ],
                'scopeMetrics' => [[
                    'metrics' => [
                        [
                            'name' => 'http.server.request.duration',
                            'histogram' => [
                                'dataPoints' => [[
                                    'count' => '10',
                                    'sum' => 1.2,
                                ]],
                            ],
                        ],
                        [
                            'name' => 'http.server.errors',
                            'sum' => [
                                'dataPoints' => [[
                                    'asInt' => '3',
                                    'timeUnixNano' => '1721491201000000000',
                                    'attributes' => [
                                        ['key' => 'error.type', 'value' => ['stringValue' => 'TimeoutException']],
                                    ],
                                ]],
                            ],
                        ],
                    ],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $payloads = $mapper->mapToEventPayloads($json);
        self::assertCount(1, $payloads);
        self::assertSame('http.server.errors', $payloads[0]['message']);
        self::assertSame('error', $payloads[0]['level']);
        self::assertSame('prod', $payloads[0]['environment']);
        self::assertSame('2.1.0', $payloads[0]['release']);
        self::assertSame('otlp', $payloads[0]['platform']);
        self::assertSame('metrics', $payloads[0]['extra']['otlp.signal']);
        self::assertSame('billing', $payloads[0]['tags']['otel.service']);
        self::assertSame('http.server.errors', $payloads[0]['tags']['otel.metric']);
        self::assertSame('TimeoutException', $payloads[0]['exception']['values'][0]['type']);

        $envelope = $mapper->toEnvelopeBody($payloads);
        self::assertStringContainsString('"type":"event"', $envelope);
        self::assertStringNotContainsString('"dsn"', $envelope);
        self::assertStringContainsString('http.server.errors', $envelope);
    }

    public function testMapsExceptionAttributeOnNonErrorName(): void
    {
        $mapper = new OtlpMetricsMapper();
        $json = json_encode([
            'resource_metrics' => [[
                'scope_metrics' => [[
                    'metrics' => [[
                        'name' => 'app.custom',
                        'gauge' => [
                            'data_points' => [[
                                'as_double' => 1.0,
                                'attributes' => [
                                    ['key' => 'exception.message', 'value' => ['string_value' => 'disk full']],
                                ],
                            ]],
                        ],
                    ]],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $payloads = $mapper->mapToEventPayloads($json);
        self::assertCount(1, $payloads);
        self::assertSame('disk full', $payloads[0]['message']);
    }

    public function testRejectsInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OtlpMetricsMapper()->mapToEventPayloads('{');
    }
}
