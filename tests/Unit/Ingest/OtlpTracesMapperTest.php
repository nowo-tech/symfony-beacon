<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\Otlp\Service\OtlpTracesMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OtlpTracesMapperTest extends TestCase
{
    public function testMapsErrorSpanAndDropsOk(): void
    {
        $mapper = new OtlpTracesMapper();
        $json = json_encode([
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'billing']],
                        ['key' => 'deployment.environment', 'value' => ['stringValue' => 'prod']],
                        ['key' => 'service.version', 'value' => ['stringValue' => '2.1.0']],
                    ],
                ],
                'scopeSpans' => [[
                    'spans' => [
                        [
                            'traceId' => 'aa',
                            'spanId' => '11',
                            'name' => 'GET /health',
                            'status' => ['code' => 1],
                        ],
                        [
                            'traceId' => 'aabb',
                            'spanId' => 'ccdd',
                            'name' => 'POST /checkout',
                            'endTimeUnixNano' => '1721491201000000000',
                            'status' => ['code' => 2, 'message' => 'boom'],
                            'attributes' => [
                                ['key' => 'exception.type', 'value' => ['stringValue' => 'RuntimeException']],
                                ['key' => 'exception.message', 'value' => ['stringValue' => 'payment failed']],
                            ],
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
        self::assertSame('traces', $payloads[0]['extra']['otlp.signal']);
        self::assertSame('billing', $payloads[0]['tags']['otel.service']);
        self::assertSame('POST /checkout', $payloads[0]['tags']['otel.span']);
        self::assertSame('aabb', $payloads[0]['contexts']['trace']['trace_id']);
        self::assertArrayHasKey('exception', $payloads[0]);

        $envelope = $mapper->toEnvelopeBody($payloads);
        self::assertStringContainsString('"type":"event"', $envelope);
        self::assertStringNotContainsString('"dsn"', $envelope);
        self::assertStringContainsString('payment failed', $envelope);
    }

    public function testRejectsInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OtlpTracesMapper()->mapToEventPayloads('{');
    }
}
