<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Beacon\DropSelfIngestBeforeSend;
use PHPUnit\Framework\TestCase;

final class DropSelfIngestBeforeSendTest extends TestCase
{
    public function testDropsEnvelopeIngestPaths(): void
    {
        $hook = new DropSelfIngestBeforeSend();

        self::assertNull($hook([
            'request' => ['url' => 'https://beacon.example/api/1/envelope/'],
        ]));
        self::assertNull($hook([
            'request' => ['path' => '/api/1/envelope'],
        ]));
        self::assertNull($hook([
            'request' => ['url' => 'https://beacon.example/api/1/otlp/v1/logs'],
        ]));
    }

    public function testKeepsDashboardExceptions(): void
    {
        $hook = new DropSelfIngestBeforeSend();
        $payload = [
            'message' => 'Boom',
            'request' => ['url' => 'https://beacon.example/dashboard'],
        ];

        self::assertSame($payload, $hook($payload));
    }

    public function testKeepsEventsWithoutRequestContext(): void
    {
        $hook = new DropSelfIngestBeforeSend();
        $payload = ['message' => 'Console failure'];

        self::assertSame($payload, $hook($payload));
    }
}
