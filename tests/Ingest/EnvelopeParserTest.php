<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Ingest\Service\EnvelopeParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnvelopeParserTest extends TestCase
{
    public function testParsesEventItem(): void
    {
        $body = implode("\n", [
            json_encode(['event_id' => 'abc', 'sent_at' => '2026-01-01T00:00:00Z'], \JSON_THROW_ON_ERROR),
            json_encode(['type' => 'event', 'length' => 2], \JSON_THROW_ON_ERROR),
            json_encode(['event_id' => 'abc', 'message' => 'boom', 'level' => 'error'], \JSON_THROW_ON_ERROR),
        ]);

        $parsed = new EnvelopeParser()->parse($body);

        self::assertSame('abc', $parsed['header']['event_id']);
        self::assertCount(1, $parsed['items']);
        self::assertSame('event', $parsed['items'][0]['header']['type']);
        self::assertIsArray($parsed['items'][0]['payload']);
        self::assertSame('boom', $parsed['items'][0]['payload']['message']);
    }

    public function testRejectsEmptyEnvelope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EnvelopeParser()->parse("\n");
    }
}
