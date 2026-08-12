<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\Otlp\Service;

use App\Ingest\Otlp\Service\OtlpAttributeCodec;
use PHPUnit\Framework\TestCase;

final class OtlpAttributeCodecTest extends TestCase
{
    public function testToEnvelopeBodyWritesNdjson(): void
    {
        $codec = $this->codec();
        $body = $codec->toEnvelopeBody([
            ['message' => 'boom', 'level' => 'error'],
        ]);
        $lines = explode("\n", $body);
        self::assertCount(3, $lines);
        $header = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('beacon-otlp', $header['sdk']['name']);
        $meta = json_decode($lines[1], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('event', $meta['type']);
        self::assertSame(\strlen($lines[2]), $meta['length']);
    }

    public function testAttributesMapCoversValueShapesAndSkipsInvalid(): void
    {
        $codec = $this->codec();
        $map = $codec->exposeAttributesMap([
            'skip',
            ['key' => ''],
            ['key' => 's', 'value' => ['stringValue' => 'hello']],
            ['key' => 'snake', 'value' => ['string_value' => 'world']],
            ['key' => 'i', 'value' => ['intValue' => 7]],
            ['key' => 'd', 'value' => ['doubleValue' => 1.5]],
            ['key' => 'b', 'value' => ['boolValue' => true]],
            ['key' => 'bf', 'value' => ['boolValue' => false]],
            ['key' => 'scalar', 'value' => 42],
            ['key' => 'empty', 'value' => ['unknown' => true]],
            ['key' => 'arr', 'value' => ['x']],
        ]);

        self::assertSame([
            's' => 'hello',
            'snake' => 'world',
            'i' => '7',
            'd' => '1.5',
            'b' => 'true',
            'bf' => 'false',
            'scalar' => '42',
        ], $map);
    }

    public function testNanoToUnix(): void
    {
        $codec = $this->codec();
        self::assertSame(1.0, $codec->exposeNanoToUnix(1_000_000_000));
        self::assertSame(2.0, $codec->exposeNanoToUnix('2000000000'));
        self::assertGreaterThan(0.0, $codec->exposeNanoToUnix('not-a-number'));
    }

    private function codec(): object
    {
        return new class {
            use OtlpAttributeCodec;

            /**
             * @param list<mixed> $attributes
             *
             * @return array<string, string>
             */
            public function exposeAttributesMap(array $attributes): array
            {
                return $this->attributesMap($attributes);
            }

            public function exposeNanoToUnix(mixed $nano): float
            {
                return $this->nanoToUnix($nano);
            }
        };
    }
}
