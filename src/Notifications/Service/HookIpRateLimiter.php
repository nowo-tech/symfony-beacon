<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Sliding-window rate limit for public Slack / Teams / inbound-email hooks (pre-auth).
 *
 * Limit {@code 0} disables the limiter.
 */
final readonly class HookIpRateLimiter
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        #[Autowire('%beacon.hook_ip_rate_limit%')]
        private int $limitPerMinute,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->limitPerMinute > 0;
    }

    public function accept(string $clientIp): bool
    {
        if ($this->limitPerMinute <= 0) {
            return true;
        }

        $ip = '' !== $clientIp ? $clientIp : 'unknown';

        $factory = new RateLimiterFactory([
            'id' => 'beacon_hook_ip',
            'policy' => 'sliding_window',
            'limit' => $this->limitPerMinute,
            'interval' => '1 minute',
        ], new CacheStorage($this->cache));

        return $factory->create('ip_'.hash('xxh128', $ip))->consume(1)->isAccepted();
    }
}
