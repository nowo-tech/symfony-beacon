<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Notifications\Service\WebPushEndpointGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebPushEndpointGuardTest extends TestCase
{
    #[DataProvider('allowedEndpoints')]
    public function testAllowsKnownPushHosts(string $endpoint): void
    {
        new WebPushEndpointGuard()->assertSafeEndpoint($endpoint);
        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function allowedEndpoints(): iterable
    {
        yield 'fcm' => ['https://fcm.googleapis.com/fcm/send/abc'];
        yield 'fcm subdomain' => ['https://xxx.fcm.googleapis.com/wp/abc'];
        yield 'mozilla' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'];
        yield 'apple' => ['https://web.push.apple.com/abc'];
    }

    #[DataProvider('rejectedEndpoints')]
    public function testRejectsUnsafeEndpoints(string $endpoint): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WebPushEndpointGuard()->assertSafeEndpoint($endpoint);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function rejectedEndpoints(): iterable
    {
        yield 'http' => ['http://fcm.googleapis.com/fcm/send/abc'];
        yield 'private ip' => ['https://127.0.0.1/push'];
        yield 'metadata' => ['https://169.254.169.254/latest/meta-data/'];
        yield 'arbitrary host' => ['https://evil.example/push'];
        yield 'empty' => [''];
    }
}
