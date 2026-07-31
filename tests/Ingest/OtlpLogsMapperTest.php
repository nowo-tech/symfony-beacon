<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Ingest\Service\OtlpLogsMapper;
use PHPUnit\Framework\TestCase;

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
        $this->expectException(\InvalidArgumentException::class);
        (new OtlpLogsMapper())->mapToEventPayloads('{');
    }
}
