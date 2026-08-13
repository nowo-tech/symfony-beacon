<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\Service\EnvelopeAuthParser;
use PHPUnit\Framework\TestCase;

final class EnvelopeAuthParserTest extends TestCase
{
    private EnvelopeAuthParser $parser;

    protected function setUp(): void
    {
        $this->parser = new EnvelopeAuthParser();
    }

    public function testQueryContainsCredentialsDetectsKeyOrSecret(): void
    {
        self::assertTrue($this->parser->queryContainsCredentials('beacon_key=abc'));
        self::assertTrue($this->parser->queryContainsCredentials('beacon_secret=xyz'));
        self::assertTrue($this->parser->queryContainsCredentials('beacon_key=a&beacon_secret=b'));
        self::assertFalse($this->parser->queryContainsCredentials(''));
        self::assertFalse($this->parser->queryContainsCredentials('foo=bar'));
        self::assertFalse($this->parser->queryContainsCredentials('beacon_key='));
    }

    public function testParseFromRequestIgnoresQueryEvenIfPassedHistorically(): void
    {
        $auth = $this->parser->parseFromRequest(
            'Beacon beacon_key=from-header, beacon_secret=hdr-secret',
        );

        self::assertSame('from-header', $auth['public_key']);
        self::assertSame('hdr-secret', $auth['secret_key']);
    }

    public function testParseFromRequestUsesEnvelopeDsnWhenHeaderMissing(): void
    {
        $auth = $this->parser->parseFromRequest(
            null,
            'https://pub:sec@beacon.example/019fea2d-507b-7890-8b33-ca488db6f696',
        );

        self::assertSame('pub', $auth['public_key']);
        self::assertSame('sec', $auth['secret_key']);
    }

    public function testParseFromRequestWithoutCredentialsReturnsNulls(): void
    {
        $auth = $this->parser->parseFromRequest(null);

        self::assertNull($auth['public_key']);
        self::assertNull($auth['secret_key']);
    }
}
