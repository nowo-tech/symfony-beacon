<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Ingest\Service\IngestRateLimiter;
use App\Tests\Shared\DatabaseWebTestCase;
use Psr\Cache\CacheItemPoolInterface;

final class IngestRateLimitTest extends DatabaseWebTestCase
{
    public function testLimiterRejectsAfterLimit(): void
    {
        self::createClient();
        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);

        $limiter = new IngestRateLimiter($cache, 2);
        self::assertTrue($limiter->accept(42));
        self::assertTrue($limiter->accept(42));
        self::assertFalse($limiter->accept(42));
        // Other projects are independent
        self::assertTrue($limiter->accept(43));
    }

    public function testDisabledLimitAlwaysAccepts(): void
    {
        self::createClient();
        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $limiter = new IngestRateLimiter($cache, 0);
        self::assertTrue($limiter->accept(1));
        self::assertTrue($limiter->accept(1));
    }
}
