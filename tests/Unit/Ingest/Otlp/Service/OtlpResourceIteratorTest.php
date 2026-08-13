<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\Otlp\Service;

use App\Ingest\Otlp\Service\OtlpResourceIterator;
use PHPUnit\Framework\TestCase;

final class OtlpResourceIteratorTest extends TestCase
{
    public function testWalksCamelAndSnakeKeysAndMapsAttributes(): void
    {
        $seen = [];
        OtlpResourceIterator::walk(
            [
                'resourceSpans' => [
                    [
                        'resource' => [
                            'attributes' => [['key' => 'service.name', 'value' => ['stringValue' => 'api']]],
                        ],
                        'scopeSpans' => [
                            [
                                'spans' => [
                                    ['name' => 'GET /'],
                                    'skip-me',
                                ],
                            ],
                            'skip-scope',
                        ],
                    ],
                    'skip-resource',
                ],
            ],
            'resourceSpans',
            'resource_spans',
            'scopeSpans',
            'scope_spans',
            'spans',
            'spans',
            static fn (array $attrs): array => ['service.name' => 'api'],
            static function (array $resourceAttrs, array $record) use (&$seen): bool {
                $seen[] = [$resourceAttrs, $record];

                return true;
            },
        );

        self::assertSame(
            [
                [['service.name' => 'api'], ['name' => 'GET /']],
            ],
            $seen,
        );
    }

    public function testSnakeCaseKeysAndStopOnFalse(): void
    {
        $count = 0;
        OtlpResourceIterator::walk(
            [
                'resource_logs' => [
                    [
                        'resource' => ['attributes' => []],
                        'scope_logs' => [
                            [
                                'log_records' => [
                                    ['body' => 'one'],
                                    ['body' => 'two'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'resourceLogs',
            'resource_logs',
            'scopeLogs',
            'scope_logs',
            'logRecords',
            'log_records',
            static fn (array $attrs): array => [],
            static function (array $resourceAttrs, array $record) use (&$count): bool {
                ++$count;

                return false;
            },
        );

        self::assertSame(1, $count);
    }

    public function testIgnoresNonArrayResourcesRoot(): void
    {
        $called = false;
        OtlpResourceIterator::walk(
            ['resourceSpans' => 'nope'],
            'resourceSpans',
            'resource_spans',
            'scopeSpans',
            'scope_spans',
            'spans',
            'spans',
            static fn (array $attrs): array => [],
            static function () use (&$called): bool {
                $called = true;

                return true;
            },
        );

        self::assertFalse($called);
    }
}
