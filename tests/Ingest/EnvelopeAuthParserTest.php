<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Ingest\Service\EnvelopeAuthParser;
use PHPUnit\Framework\TestCase;

final class EnvelopeAuthParserTest extends TestCase
{
    public function testParsesHeader(): void
    {
        $parser = new EnvelopeAuthParser();
        $result = $parser->parseFromRequest(
            'Beacon beacon_key=publickey, beacon_secret=secret, beacon_client=php/1.5',
            '',
        );

        self::assertSame('publickey', $result['public_key']);
        self::assertSame('secret', $result['secret_key']);
    }

    public function testParsesQueryAndDsn(): void
    {
        $parser = new EnvelopeAuthParser();
        $fromQuery = $parser->parseFromRequest(null, 'beacon_key=qkey&beacon_secret=sec');
        self::assertSame('qkey', $fromQuery['public_key']);
        self::assertSame('sec', $fromQuery['secret_key']);

        $fromDsn = $parser->parseFromRequest(null, '', 'https://dsnkey:dsnsecret@localhost/1');
        self::assertSame('dsnkey', $fromDsn['public_key']);
        self::assertSame('dsnsecret', $fromDsn['secret_key']);
    }
}
