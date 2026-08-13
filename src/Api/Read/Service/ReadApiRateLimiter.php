<?php

declare(strict_types=1);

namespace App\Api\Read\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Sliding-window Read API rate limit keyed by client IP and optional token id.
 *
 * Limit {@code 0} disables the limiter (useful in some test profiles).
 */
final readonly class ReadApiRateLimiter
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        #[Autowire('%beacon.read_api_rate_limit%')]
        private int $limitPerMinute,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->limitPerMinute > 0;
    }

    public function accept(string $clientIp, ?string $tokenKey = null): bool
    {
        if ($this->limitPerMinute <= 0) {
            return true;
        }

        $ip = '' !== $clientIp ? $clientIp : 'unknown';
        $key = null !== $tokenKey && '' !== $tokenKey
            ? 'tok_'.hash('xxh128', $tokenKey)
            : 'ip_'.hash('xxh128', $ip);

        $factory = new RateLimiterFactory([
            'id' => 'beacon_read_api',
            'policy' => 'sliding_window',
            'limit' => $this->limitPerMinute,
            'interval' => '1 minute',
        ], new CacheStorage($this->cache));

        return $factory->create($key)->consume(1)->isAccepted();
    }
}
