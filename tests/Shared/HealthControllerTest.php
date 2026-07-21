<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Health\HealthController;
use App\Shared\Health\MessengerQueueHealth;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends TestCase
{
    public function testReadyDoesNotEchoExceptionMessage(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(
            new RuntimeException('SQLSTATE secret-db-host password=leaked'),
        );

        $controller = new HealthController($em, new MessengerQueueHealth($em), new NullLogger());

        $response = $controller->ready();
        $payload = json_decode($response->getContent() ?: '[]', true);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame('error', $payload['status'] ?? null);
        self::assertSame('unavailable', $payload['error'] ?? null);
        self::assertStringNotContainsString('leaked', $response->getContent() ?: '');
        self::assertStringNotContainsString('SQLSTATE', $response->getContent() ?: '');
    }
}
