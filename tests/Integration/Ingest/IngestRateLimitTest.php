<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ingest;

use App\Ingest\Service\IngestRateLimiter;
use App\Tests\Support\DatabaseWebTestCase;
use Psr\Cache\CacheItemPoolInterface;

final class IngestRateLimitTest extends DatabaseWebTestCase
{
    public function testLimiterRejectsAfterLimit(): void
    {
        self::createClient();
        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);

        $limiter = new IngestRateLimiter($cache);
        self::assertTrue($limiter->accept(42, 2));
        self::assertTrue($limiter->accept(42, 2));
        self::assertFalse($limiter->accept(42, 2));
        // Other projects are independent
        self::assertTrue($limiter->accept(43, 2));
    }

    public function testDisabledLimitAlwaysAccepts(): void
    {
        self::createClient();
        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $limiter = new IngestRateLimiter($cache);
        self::assertTrue($limiter->accept(1, 0));
        self::assertTrue($limiter->accept(1, 0));
    }
}
