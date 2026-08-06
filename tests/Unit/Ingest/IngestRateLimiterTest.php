<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\Service\IngestRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class IngestRateLimiterTest extends TestCase
{
    public function testDisabledLimitAlwaysAccepts(): void
    {
        $limiter = new IngestRateLimiter(new ArrayAdapter());

        self::assertFalse($limiter->isEnabled(0));
        self::assertTrue($limiter->accept(1, 0));
        self::assertTrue($limiter->accept(1, -1));
    }

    public function testSlidingWindowRejectsAfterLimit(): void
    {
        $limiter = new IngestRateLimiter(new ArrayAdapter());

        self::assertTrue($limiter->isEnabled(2));
        self::assertTrue($limiter->accept(42, 2));
        self::assertTrue($limiter->accept(42, 2));
        self::assertFalse($limiter->accept(42, 2));
        self::assertTrue($limiter->accept(99, 2));
    }
}
