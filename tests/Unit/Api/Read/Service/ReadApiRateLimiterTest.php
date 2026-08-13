<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Read\Service;

use App\Api\Read\Service\ReadApiRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ReadApiRateLimiterTest extends TestCase
{
    public function testDisabledLimitAlwaysAccepts(): void
    {
        $limiter = new ReadApiRateLimiter(new ArrayAdapter(), 0);

        self::assertFalse($limiter->isEnabled());
        self::assertTrue($limiter->accept('127.0.0.1'));
        self::assertTrue($limiter->accept('127.0.0.1', 'tok'));
    }

    public function testEnforcesSlidingWindowPerIpAndToken(): void
    {
        $limiter = new ReadApiRateLimiter(new ArrayAdapter(), 2);

        self::assertTrue($limiter->isEnabled());
        self::assertTrue($limiter->accept('1.2.3.4'));
        self::assertTrue($limiter->accept('1.2.3.4'));
        self::assertFalse($limiter->accept('1.2.3.4'));

        self::assertTrue($limiter->accept('9.9.9.9'));
        self::assertTrue($limiter->accept('1.2.3.4', 'token-a'));
        self::assertTrue($limiter->accept('1.2.3.4', 'token-a'));
        self::assertFalse($limiter->accept('1.2.3.4', 'token-a'));
        self::assertTrue($limiter->accept('1.2.3.4', 'token-b'));
    }
}
