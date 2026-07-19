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
            'Sentry sentry_version=7, sentry_key=publickey, sentry_client=sentry.php/4.0',
            '',
        );

        self::assertSame('publickey', $result['sentry_key']);
    }

    public function testParsesQueryAndDsn(): void
    {
        $parser = new EnvelopeAuthParser();
        $fromQuery = $parser->parseFromRequest(null, 'sentry_key=qkey&sentry_secret=sec');
        self::assertSame('qkey', $fromQuery['sentry_key']);
        self::assertSame('sec', $fromQuery['sentry_secret']);

        $fromDsn = $parser->parseFromRequest(null, '', 'https://dsnkey:dsnsecret@localhost/1');
        self::assertSame('dsnkey', $fromDsn['sentry_key']);
        self::assertSame('dsnsecret', $fromDsn['sentry_secret']);
    }
}
