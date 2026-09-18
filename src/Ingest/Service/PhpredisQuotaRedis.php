<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use Redis;
use RuntimeException;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Throwable;

/**
 * phpredis adapter for {@see QuotaRedis}. Excluded from the coverage gate
 * (needs a live Redis). {@see EventQuotaUsageStore} is tested with a fake.
 */
#[WhenNot(env: 'test')]
final class PhpredisQuotaRedis implements QuotaRedis
{
    private ?Redis $redis = null;

    public function __construct(
        #[Autowire('%env(REDIS_URL)%')]
        private readonly string $redisUrl,
    ) {
    }

    public function set(string $key, string $value, array $options): mixed
    {
        return $this->client()->set($key, $value, $options);
    }

    public function get(string $key): mixed
    {
        return $this->client()->get($key);
    }

    public function incr(string $key): int|false
    {
        $value = $this->client()->incr($key);

        return \is_int($value) ? $value : false;
    }

    public function expireAt(string $key, int $timestamp): bool
    {
        return (bool) $this->client()->expireAt($key, $timestamp);
    }

    private function client(): Redis
    {
        if ($this->redis instanceof Redis) {
            return $this->redis;
        }

        try {
            $client = RedisAdapter::createConnection($this->redisUrl, [
                'timeout' => 1.0,
                'lazy' => false,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Quota Redis is unavailable.', 0, $e);
        }

        if (!$client instanceof Redis) {
            throw new RuntimeException('Quota Redis requires the phpredis extension.');
        }

        $this->redis = $client;

        return $client;
    }
}
