<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Service\HookIpRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class HookIpRateLimiterTest extends TestCase
{
    public function testDisabledWhenLimitZero(): void
    {
        $limiter = new HookIpRateLimiter(new ArrayAdapter(), 0);
        self::assertFalse($limiter->isEnabled());
        self::assertTrue($limiter->accept('127.0.0.1'));
    }

    public function testRejectsAfterBudgetExhausted(): void
    {
        $limiter = new HookIpRateLimiter(new ArrayAdapter(), 2);
        self::assertTrue($limiter->isEnabled());
        self::assertTrue($limiter->accept('10.0.0.1'));
        self::assertTrue($limiter->accept('10.0.0.1'));
        self::assertFalse($limiter->accept('10.0.0.1'));
        self::assertTrue($limiter->accept('10.0.0.2'));
    }
}
