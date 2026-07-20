<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Ingest\Service\EventTimestampParser;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class EventTimestampParserTest extends TestCase
{
    public function testParsesFractionalUnixTimestamp(): void
    {
        $parser = new EventTimestampParser();
        $parsed = $parser->parse(1721491200.123456);

        self::assertNotNull($parsed);
        self::assertSame('2024-07-20 16:00:00.123456', $parsed->format('Y-m-d H:i:s.u'));
    }

    public function testParsesIsoDatetimeString(): void
    {
        $parser = new EventTimestampParser();
        $parsed = $parser->parse('2026-07-20T18:30:45.654321Z');

        self::assertNotNull($parsed);
        self::assertSame('2026-07-20 18:30:45.654321', $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'));
    }

    public function testReturnsNullForInvalidValues(): void
    {
        $parser = new EventTimestampParser();

        self::assertNull($parser->parse(null));
        self::assertNull($parser->parse([]));
        self::assertNull($parser->parse('not-a-date'));
    }
}
