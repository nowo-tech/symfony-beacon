<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Health\HealthController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
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

    public function testReadyOutsideTestFailsClosedWhenRedisIsDown(): void
    {
        $controller = new HealthController($this->readyEntityManager(), new NullLogger(), 'prod', new FixedRedisProbe(false));

        $response = $controller->ready();
        $payload = json_decode($response->getContent() ?: '[]', true);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame('unavailable', $payload['error'] ?? null);
        self::assertSame(['database' => true, 'redis' => false], $payload['checks'] ?? null);
        self::assertStringNotContainsString('unavailable.', $response->getContent() ?: '');
    }

    public function testReadyOutsideTestReportsRedis(): void
    {
        $response = new HealthController($this->readyEntityManager(), new NullLogger(), 'prod', new FixedRedisProbe(true))->ready();
        $payload = json_decode($response->getContent() ?: '[]', true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame(['database' => true, 'redis' => true], $payload['checks'] ?? null);
    }

    private function readyEntityManager(): EntityManagerInterface
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($this->createStub(Result::class));
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        return $em;
    }
}

final class FixedRedisProbe implements \App\Shared\Health\RedisProbe
{
    public function __construct(
        private bool $up,
    ) {
    }

    public function ping(): bool
    {
        return $this->up;
    }
}
