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

    public function testMapsExceptionOnlySpanAndStringStatusCodes(): void
    {
        $mapper = new OtlpTracesMapper();
        $json = json_encode([
            'resource_spans' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'deployment.environment', 'value' => ['string_value' => 'stage']],
                        ['key' => 'service.name', 'value' => ['string_value' => 'worker']],
                    ],
                ],
                'scope_spans' => [[
                    'spans' => [
                        [
                            'trace_id' => 'trace-snake',
                            'span_id' => 'span-snake',
                            'name' => '   ',
                            'start_time_unix_nano' => '1721491203000000000',
                            'attributes' => [
                                ['key' => 'exception.stacktrace', 'value' => ['string_value' => "Span.php:10\nnext line"]],
                            ],
                        ],
                        [
                            'name' => 'Process order',
                            'status' => ['code' => 'ERROR', 'message' => ''],
                        ],
                    ],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $payloads = $mapper->mapToEventPayloads($json);
        self::assertCount(2, $payloads);
        self::assertSame('OTLP span', $payloads[0]['message']);
        self::assertSame('stage', $payloads[0]['environment']);
        self::assertSame('worker', $payloads[0]['tags']['otel.service']);
        self::assertSame('trace-snake', $payloads[0]['contexts']['trace']['trace_id']);
        self::assertSame('span-snake', $payloads[0]['contexts']['trace']['span_id']);
        self::assertSame('OtlpSpanError', $payloads[0]['exception']['values'][0]['type']);
        self::assertSame('OTLP span', $payloads[0]['exception']['values'][0]['value']);
        self::assertSame('Span.php:10', $payloads[0]['exception']['values'][0]['stacktrace']['frames'][0]['filename']);
        self::assertSame('Process order', $payloads[1]['message']);
        self::assertSame('Process order', $payloads[1]['exception']['values'][0]['value']);
    }

    public function testRejectsScalarJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OtlpTracesMapper()->mapToEventPayloads('"scalar"');
    }

    public function testStopsAtMaxSpansAndLeavesEnvironmentUnsetWhenMissing(): void
    {
        $spans = [];
        for ($i = 0; $i < OtlpTracesMapper::MAX_SPANS + 1; ++$i) {
            $spans[] = [
                'traceId' => 'trace-'.$i,
                'spanId' => 'span-'.$i,
                'name' => 'Span '.$i,
                'status' => ['code' => OtlpTracesMapper::STATUS_CODE_ERROR],
                'endTimeUnixNano' => (string) (1721491201000000000 + $i),
            ];
        }

        $payloads = new OtlpTracesMapper()->mapToEventPayloads(json_encode([
            'resourceSpans' => [[
                'resource' => ['attributes' => []],
                'scopeSpans' => [[
                    'spans' => $spans,
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR));

        self::assertCount(OtlpTracesMapper::MAX_SPANS, $payloads);
        self::assertArrayNotHasKey('environment', $payloads[0]);
    }

    public function testTreatsUnknownStatusShapesAsNonErrorUnlessExceptionAttributesExist(): void
    {
        $payloads = new OtlpTracesMapper()->mapToEventPayloads(json_encode([
            'resourceSpans' => [[
                'resource' => ['attributes' => []],
                'scopeSpans' => [[
                    'spans' => [[
                        'name' => 'Queue worker',
                        'status' => ['code' => ['unexpected']],
                        'attributes' => [
                            ['key' => 'exception.message', 'value' => ['stringValue' => 'worker failed']],
                        ],
                    ]],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR));

        self::assertCount(1, $payloads);
        self::assertSame('worker failed', $payloads[0]['message']);
    }
}
