<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\Otlp\Service\OtlpLogsMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class OtlpLogsMapperTest extends TestCase
{
    public function testMapsErrorLogAndDropsDebug(): void
    {
        $mapper = new OtlpLogsMapper();
        $json = json_encode([
            'resourceLogs' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'billing']],
                        ['key' => 'deployment.environment', 'value' => ['stringValue' => 'prod']],
                        ['key' => 'service.version', 'value' => ['stringValue' => '2.1.0']],
                    ],
                ],
                'scopeLogs' => [[
                    'logRecords' => [
                        [
                            'timeUnixNano' => '1721491200000000000',
                            'severityNumber' => 5,
                            'severityText' => 'DEBUG',
                            'body' => ['stringValue' => 'noise'],
                        ],
                        [
                            'timeUnixNano' => '1721491201000000000',
                            'severityNumber' => 17,
                            'severityText' => 'ERROR',
                            'body' => ['stringValue' => 'payment failed'],
                            'attributes' => [
                                ['key' => 'exception.type', 'value' => ['stringValue' => 'RuntimeException']],
                                ['key' => 'exception.message', 'value' => ['stringValue' => 'payment failed']],
                            ],
                            'traceId' => 'aabb',
                            'spanId' => 'ccdd',
                        ],
                    ],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $payloads = $mapper->mapToEventPayloads($json);
        self::assertCount(1, $payloads);
        self::assertSame('payment failed', $payloads[0]['message']);
        self::assertSame('error', $payloads[0]['level']);
        self::assertSame('prod', $payloads[0]['environment']);
        self::assertSame('2.1.0', $payloads[0]['release']);
        self::assertSame('otlp', $payloads[0]['platform']);
        self::assertSame('billing', $payloads[0]['tags']['otel.service']);
        self::assertArrayHasKey('exception', $payloads[0]);
        self::assertSame(32, \strlen((string) $payloads[0]['event_id']));

        $envelope = $mapper->toEnvelopeBody($payloads);
        self::assertStringContainsString('"type":"event"', $envelope);
        self::assertStringNotContainsString('"dsn"', $envelope);
        self::assertStringContainsString('payment failed', $envelope);
    }

    public function testRejectsInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OtlpLogsMapper()->mapToEventPayloads('{');
    }

    public function testMapsSeverityTextFallbacksAndRejectsScalarJson(): void
    {
        $mapper = new OtlpLogsMapper();
        $json = json_encode([
            'resource_logs' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'deployment.environment.name', 'value' => ['string_value' => 'staging']],
                    ],
                ],
                'scope_logs' => [[
                    'log_records' => [
                        [
                            'time_unix_nano' => '1721491202000000000',
                            'severityText' => 'WARNING',
                            'body' => ['boolValue' => true],
                            'attributes' => [
                                ['key' => 'service.name', 'value' => ['string_value' => 'queue']],
                                ['key' => 'service.version', 'value' => ['string_value' => '3.0.0']],
                                ['key' => 'exception.type.name', 'value' => ['string_value' => 'QueueException']],
                                ['key' => 'exception.stacktrace', 'value' => ['string_value' => "Queue.php:45\nnext line"]],
                            ],
                            'spanId' => 'span-only',
                        ],
                        [
                            'severityText' => 'INFORMATION',
                            'body' => ['string_value' => 'ignore me'],
                        ],
                    ],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $payloads = $mapper->mapToEventPayloads($json);
        self::assertCount(1, $payloads);
        self::assertSame('OTLP log', $payloads[0]['message']);
        self::assertSame('warning', $payloads[0]['level']);
        self::assertSame('staging', $payloads[0]['environment']);
        self::assertSame('3.0.0', $payloads[0]['release']);
        self::assertSame('queue', $payloads[0]['tags']['otel.service']);
        self::assertSame('WARNING', $payloads[0]['tags']['otel.severity']);
        self::assertSame('span-only', $payloads[0]['contexts']['trace']['span_id']);
        self::assertSame('QueueException', $payloads[0]['exception']['values'][0]['type']);
        self::assertSame('OTLP log', $payloads[0]['exception']['values'][0]['value']);
        self::assertSame('Queue.php:45', $payloads[0]['exception']['values'][0]['stacktrace']['frames'][0]['filename']);

        $this->expectException(InvalidArgumentException::class);
        $mapper->mapToEventPayloads('"scalar"');
    }

    public function testCoversHelperFallbacksAndRecordLimit(): void
    {
        $mapper = new OtlpLogsMapper();

        $extractBody = new ReflectionMethod(OtlpLogsMapper::class, 'extractBody');
        self::assertSame('', $extractBody->invoke($mapper, 123));
        self::assertSame('snake-case body', $extractBody->invoke($mapper, ['string_value' => 'snake-case body']));

        $mapLevel = new ReflectionMethod(OtlpLogsMapper::class, 'mapLevel');
        self::assertSame('fatal', $mapLevel->invoke($mapper, 0, 'CRITICAL'));
        self::assertSame('error', $mapLevel->invoke($mapper, 0, ''));

        $records = [];
        for ($i = 0; $i < OtlpLogsMapper::MAX_RECORDS; ++$i) {
            $records[] = [
                'severityNumber' => 17,
                'body' => ['stringValue' => 'failure '.$i],
            ];
        }
        $payloads = $mapper->mapToEventPayloads(json_encode([
            'resourceLogs' => [[
                'scopeLogs' => [[
                    'logRecords' => $records,
                ]],
            ], [
                'scopeLogs' => [[
                    'logRecords' => [[
                        'severityNumber' => 17,
                        'body' => ['stringValue' => 'skipped'],
                    ]],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR));
        self::assertCount(OtlpLogsMapper::MAX_RECORDS, $payloads);

        $fallback = $mapper->mapToEventPayloads(json_encode([
            'resourceLogs' => [[
                'scopeLogs' => [[
                    'logRecords' => [[
                        'body' => 42,
                    ]],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR));
        self::assertCount(1, $fallback);
        self::assertSame('OTLP log', $fallback[0]['message']);
    }
}
