<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Symfony\Component\HttpFoundation\Request;

final class HealthEndpointsTest extends DatabaseWebTestCase
{
    public function testLiveAndReadyArePublic(): void
    {
        // Ensures schema exists (DatabaseWebTestCase recreates it per boot).
        [$client] = $this->bootWithDemoProject('health@example.com');

        $client->request(Request::METHOD_GET, '/health/live');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"status":"ok"', $client->getResponse()->getContent() ?: '');

        $client->request(Request::METHOD_GET, '/health/ready');
        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '[]', true);
        self::assertIsArray($payload);
        self::assertSame('ok', $payload['status'] ?? null);
        self::assertTrue($payload['checks']['database'] ?? false);
    }
}
