<?php

declare(strict_types=1);

namespace App\Shared\Health;

use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Liveness and readiness probes for orchestrators (no auth).
 *
 * Readiness checks the database, and Redis outside the test environment
 * (session, cache, and Messenger share REDIS_URL). The test environment skips
 * Redis: PHPUnit has no Redis service. Messenger backlog stays on
 * authenticated {@code /metrics}.
 */
#[AsController]
final readonly class HealthController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private string $environment = 'test',
        private ?RedisProbe $redis = null,
    ) {
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    #[OA\Get(path: '/health/live', operationId: 'healthLive', description: 'Returns 200 when the PHP process can serve HTTP. Does not check the database.', summary: 'Liveness probe', security: [], tags: ['Health'])]
    #[OA\Response(
        response: 200,
        description: 'Process is alive. Includes non-secret FrankenPHP runtime signals for worker-safety checks.',
        content: new OA\JsonContent(
            required: ['status', 'runtime'],
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'ok'),
                new OA\Property(
                    property: 'runtime',
                    properties: [
                        new OA\Property(property: 'frankenphp_mode', type: 'string', example: 'worker'),
                        new OA\Property(property: 'frankenphp_worker', type: 'boolean', example: true),
                        new OA\Property(property: 'reset_kernel', type: 'boolean', example: false),
                        new OA\Property(property: 'app_runtime_mode', type: 'string', example: 'web=1&worker=1', nullable: true),
                        new OA\Property(property: 'worker_num', type: 'integer', example: 1, nullable: true),
                    ],
                    type: 'object',
                ),
            ],
            type: 'object',
        ),
    )]
    public function live(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'runtime' => FrankenPhpRuntime::snapshot(),
        ]);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    #[OA\Get(path: '/health/ready', operationId: 'healthReady', description: 'Checks database connectivity, and Redis outside the test environment. Does not expose Messenger queue depth (use `/metrics` when authorized).', summary: 'Readiness probe', security: [], tags: ['Health'])]
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
                    ],
                    type: 'object',
                ),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(
        response: 503,
        description: 'Database (or other readiness check) failed. Error detail is generic (no exception text).',
        content: new OA\JsonContent(
            required: ['status', 'checks', 'error'],
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'error'),
                new OA\Property(property: 'checks', type: 'object'),
                new OA\Property(property: 'error', type: 'string', example: 'unavailable'),
            ],
            type: 'object',
        ),
    )]
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => false,
        ];
        $redis = $this->redis;
        if ($redis instanceof RedisProbe && 'test' !== $this->environment) {
            $checks['redis'] = false;
        }

        try {
            $connection = $this->entityManager->getConnection();
            $connection->executeQuery('SELECT 1');
            $checks['database'] = true;
            if ($redis instanceof RedisProbe && 'test' !== $this->environment) {
                if (!$redis->ping()) {
                    throw new RuntimeException('Redis is unavailable.');
                }
                $checks['redis'] = true;
            }
        } catch (Throwable $e) {
            $this->logger->error('Readiness probe failed.', [
                'exception' => $e,
            ]);

            return new JsonResponse([
                'status' => 'error',
                'checks' => $checks,
                'error' => 'unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse([
            'status' => 'ok',
            'checks' => $checks,
        ]);
    }
}
