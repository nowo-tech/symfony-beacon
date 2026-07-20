<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Sliding-window ingest rate limit keyed by project id (API key storms).
 */
final readonly class IngestRateLimiter
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private int $limitPerMinute,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->limitPerMinute > 0;
    }

    public function accept(int $projectId): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $factory = new RateLimiterFactory([
            'id' => 'beacon_ingest',
            'policy' => 'sliding_window',
            'limit' => $this->limitPerMinute,
            'interval' => '1 minute',
        ], new CacheStorage($this->cache));

        return $factory->create('project_'.$projectId)->consume(1)->isAccepted();
    }
}
