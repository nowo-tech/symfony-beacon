<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Mercure;

use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use PHPUnit\Framework\TestCase;

final class MercureHubUrlGuardTest extends TestCase
{
    public function testAllowsDockerServiceHostnamesWithoutDnsResolve(): void
    {
        $guard = new MercureHubUrlGuard();

        self::assertSame(MercureHubUrlGuard::RESULT_VALID, $guard->classifyHttpUrl('http://mercure/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_VALID, $guard->classifyHttpUrl('http://php/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_VALID, $guard->classifyHttpUrl('https://hub.example.com/.well-known/mercure'));
    }

    public function testRejectsPrivateIpLiteralsAndLocalhostByDefault(): void
    {
        $guard = new MercureHubUrlGuard();

        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('http://127.0.0.1/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('http://192.168.1.10/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('http://10.0.0.5/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('https://localhost/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('http://hub.local/.well-known/mercure'));
    }

    public function testAllowPrivateUrlsOptInPermitsLanButNotCloudMetadata(): void
    {
        $settings = InstanceSettings::defaults()->setAllowPrivateUrls(true);
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);
        $guard = new MercureHubUrlGuard(new InstanceOpsDefaults($repo));

        self::assertSame(MercureHubUrlGuard::RESULT_VALID, $guard->classifyHttpUrl('http://127.0.0.1/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_VALID, $guard->classifyHttpUrl('http://192.168.1.10/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_VALID, $guard->classifyHttpUrl('https://localhost/.well-known/mercure'));
        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('http://169.254.169.254/latest/meta-data'));
        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('http://metadata.google.internal/computeMetadata/v1'));
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
        self::assertSame(MercureHubUrlGuard::RESULT_UNSAFE, $guard->classifyHttpUrl('http://metadata/.well-known/mercure'));
    }
}
