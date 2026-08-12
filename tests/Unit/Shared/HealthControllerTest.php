<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use App\Shared\Health\HealthController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends TestCase
{
    public function testReadyDoesNotEchoExceptionMessage(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(
            new RuntimeException('SQLSTATE secret-db-host password=leaked'),
        );

        $controller = new HealthController($em, new NullLogger());

        $response = $controller->ready();
        $payload = json_decode($response->getContent() ?: '[]', true);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame('error', $payload['status'] ?? null);
        self::assertSame('unavailable', $payload['error'] ?? null);
        self::assertStringNotContainsString('leaked', $response->getContent() ?: '');
        self::assertStringNotContainsString('SQLSTATE', $response->getContent() ?: '');
    }

    public function testReadyPayloadOmitsMessengerQueueDepth(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn(
            $this->createStub(Result::class),
        );
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $response = new HealthController($em, new NullLogger())->ready();
        $payload = json_decode($response->getContent() ?: '[]', true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertArrayHasKey('checks', $payload);
        self::assertArrayNotHasKey('messenger_async_pending', $payload['checks']);
        self::assertSame(['database' => true], $payload['checks']);
    }
}
