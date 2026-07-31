<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Sliding-window ingest rate limit keyed by project id (API key storms).
 *
 * The caller provides the effective project or instance limit.
 */
final readonly class IngestRateLimiter
{
    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function isEnabled(int $limitPerMinute): bool
    {
        return $limitPerMinute > 0;
    }

    public function accept(int $projectId, int $limitPerMinute): bool
    {
        if ($limitPerMinute <= 0) {
            return true;
        }

        $factory = new RateLimiterFactory([
            'id' => 'beacon_ingest',
            'policy' => 'sliding_window',
            'limit' => $limitPerMinute,
            'interval' => '1 minute',
        ], new CacheStorage($this->cache));

        return $factory->create('project_'.$projectId)->consume(1)->isAccepted();
    }
}
