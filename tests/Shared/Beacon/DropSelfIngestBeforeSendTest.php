<?php

declare(strict_types=1);

namespace App\Tests\Shared\Beacon;

use App\Shared\Beacon\DropSelfIngestBeforeSend;
use PHPUnit\Framework\TestCase;

final class DropSelfIngestBeforeSendTest extends TestCase
{
    public function testDropsEnvelopeRequestPath(): void
    {
        $filter = new DropSelfIngestBeforeSend();
        $result = $filter([
            'message' => 'ingest failed',
            'request' => [
                'url' => 'http://127.0.0.1/api/1/envelope/',
                'method' => 'POST',
            ],
        ]);

        self::assertNull($result);
    }

    public function testDropsEnvelopeTransactionName(): void
    {
        $filter = new DropSelfIngestBeforeSend();
        $result = $filter([
            'transaction' => '/api/2/envelope/',
        ]);

        self::assertNull($result);
    }

    public function testDropsOtlpLogsPath(): void
    {
        $filter = new DropSelfIngestBeforeSend();
        self::assertNull($filter([
            'request' => ['url' => 'http://127.0.0.1/api/1/otlp/v1/logs'],
        ]));
    }

    public function testKeepsDashboardException(): void
    {
        $filter = new DropSelfIngestBeforeSend();
        $event = [
            'message' => 'boom',
            'request' => [
                'url' => 'https://localhost/projects/abc/issues/xyz',
                'method' => 'GET',
            ],
        ];

        self::assertSame($event, $filter($event));
    }
}
