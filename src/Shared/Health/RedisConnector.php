<?php

declare(strict_types=1);

namespace App\Shared\Health;

use Redis;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Pings REDIS_URL for the readiness probe. Excluded from the coverage gate:
 * the success path needs a live Redis, and CI's PHPUnit job does not run one.
 * {@see HealthController} decides when to call it.
 */
final class RedisConnector implements RedisProbe
{
    public function __construct(
        #[Autowire('%env(REDIS_URL)%')]
        private readonly string $redisUrl,
    ) {
    }

    public function ping(): bool
    {
        if ('' === $this->redisUrl) {
            return false;
        }

        try {
            $client = RedisAdapter::createConnection($this->redisUrl, [
                'timeout' => 1.0,
                'lazy' => false,
            ]);
            if (!$client instanceof Redis) {
                return false;
            }
            $pong = $client->ping();

            return true === $pong || (\is_string($pong) && str_contains(strtoupper($pong), 'PONG'));
        } catch (Throwable) {
            return false;
        }
    }
}
