<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\PrivateNetworkTarget;
use PHPUnit\Framework\TestCase;

final class PrivateNetworkTargetTest extends TestCase
{
    public function testBlocksPrivateAndReservedIps(): void
    {
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('127.0.0.1'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('10.0.0.1'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('192.168.1.1'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('169.254.169.254'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('::1'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('not-an-ip'));
        self::assertFalse(PrivateNetworkTarget::isBlockedIp('8.8.8.8'));
        self::assertFalse(PrivateNetworkTarget::isBlockedIp('1.1.1.1'));
    }

    public function testBlocksSensitiveHostnames(): void
    {
        self::assertTrue(PrivateNetworkTarget::isBlockedHostName('localhost'));
        self::assertTrue(PrivateNetworkTarget::isBlockedHostName('hub.local'));
        self::assertTrue(PrivateNetworkTarget::isBlockedHostName('svc.internal'));
        self::assertTrue(PrivateNetworkTarget::isBlockedHostName('metadata'));
        self::assertTrue(PrivateNetworkTarget::isBlockedHostName('metadata.google.internal'));
        self::assertFalse(PrivateNetworkTarget::isBlockedHostName('mercure'));
        self::assertFalse(PrivateNetworkTarget::isBlockedHostName('hub.example.com'));
    }

    public function testCloudMetadataDetection(): void
    {
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('169.254.169.254'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('fe80::1'));
        self::assertFalse(PrivateNetworkTarget::isCloudMetadataIp('8.8.8.8'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataHost('metadata'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataHost('metadata.google.internal'));
        self::assertFalse(PrivateNetworkTarget::isCloudMetadataHost('mercure'));
    }
}
