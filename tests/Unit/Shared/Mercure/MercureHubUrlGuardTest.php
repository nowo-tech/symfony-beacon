<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Mercure;

use App\Shared\Mercure\MercureHubUrlGuard;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MercureHubUrlGuardTest extends TestCase
{
    public function testAllowsPrivateAndDockerFriendlyHubUrls(): void
    {
        $guard = new MercureHubUrlGuard();

        self::assertSame(MercureHubUrlGuard::RESULT_VALID, $guard->classifyHttpUrl('http://mercure/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_VALID, $guard->classifyHttpUrl('http://127.0.0.1/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_VALID, $guard->classifyHttpUrl('http://192.168.1.10/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_VALID, $guard->classifyHttpUrl('https://localhost/.well-known/mercure'));
    }

    public function testRejectsInvalidUrls(): void
    {
        $guard = new MercureHubUrlGuard();

        self::assertSame(MercureHubUrlGuard::RESULT_INVALID, $guard->classifyHttpUrl(''));
        self::assertSame(MercureHubUrlGuard::RESULT_INVALID, $guard->classifyHttpUrl('mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_INVALID, $guard->classifyHttpUrl('ftp://mercure/.well-known/mercure'));
    }

    public function testRejectsMetadataAndLinkLocalTargets(): void
    {
        $guard = new MercureHubUrlGuard();

        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('http://169.254.169.254/latest/meta-data'));
        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('http://metadata.google.internal/computeMetadata/v1'));
        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('http://[fe80::1]/.well-known/mercure'));
    }

    public function testBlockedIpGuardTreatsUnparseableIpv6AndNonIpsAsUnsafe(): void
    {
        $guard = new MercureHubUrlGuard();
        $method = new ReflectionMethod(MercureHubUrlGuard::class, 'isBlockedIp');

        self::assertTrue($method->invoke($guard, 'fe80::1%eth0'));
        self::assertTrue($method->invoke($guard, 'definitely-not-an-ip'));
    }
}
