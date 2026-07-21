<?php

declare(strict_types=1);

namespace App\Shared\Health;

use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Liveness and readiness probes for orchestrators (no auth).
 */
#[AsController]
final readonly class HealthController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessengerQueueHealth $messengerQueueHealth,
    ) {
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    #[OA\Get(
        path: '/health/live',
        operationId: 'healthLive',
        summary: 'Liveness probe',
        description: 'Returns 200 when the PHP process can serve HTTP. Does not check the database.',
        security: [],
        tags: ['Health'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Process is alive.',
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'ok'),
            ],
            type: 'object',
        ),
    )]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    #[OA\Get(
        path: '/health/ready',
        operationId: 'healthReady',
        summary: 'Readiness probe',
        description: 'Checks database connectivity and optionally reports pending Messenger `async` messages.',
        security: [],
        tags: ['Health'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Dependencies are ready.',
        content: new OA\JsonContent(
            required: ['status', 'checks'],
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'ok'),
                new OA\Property(
                    property: 'checks',
                    properties: [
                        new OA\Property(property: 'database', type: 'boolean', example: true),
                        new OA\Property(property: 'messenger_async_pending', type: 'integer', nullable: true, example: 0),
                    ],
                    type: 'object',
                ),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(
        response: 503,
        description: 'Database (or other readiness check) failed.',
        content: new OA\JsonContent(
            required: ['status', 'checks'],
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'error'),
                new OA\Property(property: 'checks', type: 'object'),
                new OA\Property(property: 'error', type: 'string'),
            ],
            type: 'object',
        ),
    )]
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => false,
            'messenger_async_pending' => null,
        ];

        try {
            $connection = $this->entityManager->getConnection();
            $connection->executeQuery('SELECT 1');
            $checks['database'] = true;
            $queue = $this->messengerQueueHealth->asyncPending();
            $checks['messenger_async_pending'] = $queue['pending'];
        } catch (Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'checks' => $checks,
                'error' => $e->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse([
            'status' => 'ok',
            'checks' => $checks,
        ]);
    }
}
