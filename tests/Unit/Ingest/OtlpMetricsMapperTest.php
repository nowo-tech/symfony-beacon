<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\Otlp\Service\OtlpMetricsMapper;
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


    public function testMapsFailureStatusAndFallbackNames(): void
    {
        $mapper = new OtlpMetricsMapper();
        $json = json_encode([
            'resource_metrics' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'deployment.environment', 'value' => ['string_value' => 'stage']],
                        ['key' => 'service.name', 'value' => ['string_value' => 'worker']],
                    ],
                ],
                'scope_metrics' => [[
                    'metrics' => [
                        [
                            'name' => 'worker.error.rate',
                            'exponential_histogram' => [
                                'data_points' => [[
                                    'count' => '7',
                                ]],
                            ],
                        ],
                        [
                            'name' => ' ',
                            'gauge' => [
                                'data_points' => [[
                                    'as_double' => 1.5,
                                    'start_time_unix_nano' => '1721491203000000000',
                                    'attributes' => [
                                        ['key' => 'otel.status_code', 'value' => ['string_value' => 'STATUS_CODE_ERROR']],
                                    ],
                                ]],
                            ],
                        ],
                    ],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $payloads = $mapper->mapToEventPayloads($json);
        self::assertCount(2, $payloads);
        self::assertSame('worker.error.rate', $payloads[0]['message']);
        self::assertSame('7', $payloads[0]['extra']['otlp.metric_value']);
        self::assertSame('worker', $payloads[0]['tags']['otel.service']);
        self::assertSame('stage', $payloads[0]['environment']);
        self::assertSame('OTLP metric', $payloads[1]['message']);
        self::assertSame('OTLP metric', $payloads[1]['tags']['otel.metric']);
        self::assertSame('1.5', $payloads[1]['extra']['otlp.metric_value']);
        self::assertSame('OtlpMetricError', $payloads[1]['exception']['values'][0]['type']);
    }

    public function testRejectsScalarJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OtlpMetricsMapper()->mapToEventPayloads('"scalar"');
    }


    public function testCoversPrivateHelperBranchesAndDataPointLimits(): void
    {
        $mapper = new OtlpMetricsMapper();

        $dataPoints = new \ReflectionMethod(OtlpMetricsMapper::class, 'dataPoints');
        self::assertSame([], $dataPoints->invoke($mapper, ['name' => 'no-points']));

        $isFailurePoint = new \ReflectionMethod(OtlpMetricsMapper::class, 'isFailurePoint');
        self::assertTrue($isFailurePoint->invoke($mapper, 'healthy.metric', ['error.type' => 'Timeout']));
        self::assertFalse($isFailurePoint->invoke($mapper, 'healthy.metric', []));

        $numericValue = new \ReflectionMethod(OtlpMetricsMapper::class, 'numericValue');
        self::assertSame(9.5, $numericValue->invoke($mapper, ['sum' => '9.5']));
        self::assertNull($numericValue->invoke($mapper, ['sum' => 'NaN-ish']));

        $twoHundredPoints = [];
        for ($i = 0; $i < OtlpMetricsMapper::MAX_DATA_POINTS; ++$i) {
            $twoHundredPoints[] = [
                'asInt' => (string) ($i + 1),
                'attributes' => [
                    ['key' => 'error.type', 'value' => ['stringValue' => 'Boom']],
                ],
            ];
        }
        $payloads = $mapper->mapToEventPayloads(json_encode([
            'resourceMetrics' => [[
                'scopeMetrics' => [[
                    'metrics' => [
                        ['name' => 'first.errors', 'sum' => ['dataPoints' => $twoHundredPoints]],
                        ['name' => 'second.errors', 'sum' => ['dataPoints' => [[
                            'asInt' => '201',
                            'attributes' => [
                                ['key' => 'error.type', 'value' => ['stringValue' => 'Skipped']],
                            ],
                        ]]]],
                    ],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR));
        self::assertCount(OtlpMetricsMapper::MAX_DATA_POINTS, $payloads);
        self::assertSame('first.errors', $payloads[0]['message']);

        $withScalarPoint = $mapper->mapToEventPayloads(json_encode([
            'resourceMetrics' => [[
                'scopeMetrics' => [[
                    'metrics' => [[
                        'name' => 'scalar.errors',
                        'sum' => [
                            'dataPoints' => [
                                'skip-me',
                                [
                                    'count' => '2',
                                    'attributes' => [
                                        ['key' => 'error.type', 'value' => ['stringValue' => 'Broken']],
                                    ],
                                ],
                            ],
                        ],
                    ]],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR));
        self::assertCount(1, $withScalarPoint);
        self::assertSame('2', $withScalarPoint[0]['extra']['otlp.metric_value']);

        $overLimitInSingleMetric = [];
        for ($i = 0; $i < OtlpMetricsMapper::MAX_DATA_POINTS + 1; ++$i) {
            $overLimitInSingleMetric[] = [
                'asInt' => (string) ($i + 1),
                'attributes' => [
                    ['key' => 'error.type', 'value' => ['stringValue' => 'Boom']],
                ],
            ];
        }
        self::assertCount(
            OtlpMetricsMapper::MAX_DATA_POINTS,
            $mapper->mapToEventPayloads(json_encode([
                'resourceMetrics' => [[
                    'scopeMetrics' => [[
                        'metrics' => [[
                            'name' => 'single.errors',
                            'sum' => ['dataPoints' => $overLimitInSingleMetric],
                        ]],
                    ]],
                ]],
            ], \JSON_THROW_ON_ERROR)),
        );
    }

}
