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
            'Sentry sentry_version=7, sentry_key=publickey, sentry_client=php/4.0',
            '',
        );

        self::assertSame('publickey', $result['public_key']);
    }

    public function testParsesQueryAndDsn(): void
    {
        $parser = new EnvelopeAuthParser();
        $fromQuery = $parser->parseFromRequest(null, 'sentry_key=qkey&sentry_secret=sec');
        self::assertSame('qkey', $fromQuery['public_key']);
        self::assertSame('sec', $fromQuery['secret_key']);

        $fromDsn = $parser->parseFromRequest(null, '', 'https://dsnkey:dsnsecret@localhost/1');
        self::assertSame('dsnkey', $fromDsn['public_key']);
        self::assertSame('dsnsecret', $fromDsn['secret_key']);
    }
}
