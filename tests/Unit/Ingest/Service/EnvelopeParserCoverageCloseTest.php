<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\Service;

use App\Ingest\Service\EnvelopeParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnvelopeParserCoverageCloseTest extends TestCase
{
    public function testRejectsNonArrayEnvelopeAndItemHeaders(): void
    {
        $parser = new EnvelopeParser();

        try {
            $parser->parse('"header"');
            self::fail('Expected invalid envelope header');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Invalid envelope header', $e->getMessage());
        }

        try {
            $parser->parse("{}\n\"item\"\n");
            self::fail('Expected invalid item header');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Invalid item header', $e->getMessage());
        }
    }
}
