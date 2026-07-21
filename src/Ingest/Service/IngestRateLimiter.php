<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Sliding-window ingest rate limit keyed by project id (API key storms).
 *
 * Prefer a per-project override when provided; otherwise use the env default.
 */
final readonly class IngestRateLimiter
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private int $limitPerMinute,
    ) {
    }

    public function isEnabled(?int $limitOverride = null): bool
    {
        return $this->resolveLimit($limitOverride) > 0;
    }

    public function accept(int $projectId, ?int $limitOverride = null): bool
    {
        $limit = $this->resolveLimit($limitOverride);
        if ($limit <= 0) {
            return true;
        }

        $factory = new RateLimiterFactory([
            'id' => 'beacon_ingest',
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => '1 minute',
        ], new CacheStorage($this->cache));

        return $factory->create('project_'.$projectId)->consume(1)->isAccepted();
    }

    private function resolveLimit(?int $limitOverride): int
    {
        return $limitOverride ?? $this->limitPerMinute;
    }
}
