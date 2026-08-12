<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\Service;

use App\Ingest\Service\IngestIpRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class IngestIpRateLimiterTest extends TestCase
{
    public function testDisabledWhenLimitZero(): void
    {
        $limiter = new IngestIpRateLimiter(new ArrayAdapter(), 0);

        self::assertFalse($limiter->isEnabled());
        self::assertTrue($limiter->accept('127.0.0.1'));
    }

    public function testRejectsAfterLimit(): void
    {
        $limiter = new IngestIpRateLimiter(new ArrayAdapter(), 2);

        self::assertTrue($limiter->isEnabled());
        self::assertTrue($limiter->accept('10.0.0.1'));
        self::assertTrue($limiter->accept('10.0.0.1'));
        self::assertFalse($limiter->accept('10.0.0.1'));
        // Other IP still accepted.
        self::assertTrue($limiter->accept('10.0.0.2'));
    }
}
