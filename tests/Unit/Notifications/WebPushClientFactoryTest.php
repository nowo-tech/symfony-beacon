<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Service\WebPushClientFactory;
use Http\Discovery\Exception\ClassInstantiationFailedException;
use Minishlink\WebPush\VAPID;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WebPushClientFactoryTest extends TestCase
{
    public function testNotConfiguredWhenKeysEmpty(): void
    {
        $factory = new WebPushClientFactory('', '', 'mailto:ops@example.com');

        self::assertFalse($factory->isConfigured());
        self::assertSame('', $factory->getPublicKey());

        $this->expectException(RuntimeException::class);
        $factory->create();
    }

    public function testConfiguredWhenBothKeysPresent(): void
    {
        $factory = new WebPushClientFactory('public-key', 'private-key', 'mailto:ops@example.com');

        self::assertTrue($factory->isConfigured());
        self::assertSame('public-key', $factory->getPublicKey());
    }

    public function testCreateBuildsWebPushClientOrFailsOnMissingPsr17Factory(): void
    {
        $keys = VAPID::createVapidKeys();
        $factory = new WebPushClientFactory($keys['publicKey'], $keys['privateKey'], '');

        try {
            $client = $factory->create();
            self::assertSame($keys['publicKey'], $factory->getPublicKey());
            unset($client);
        } catch (ClassInstantiationFailedException $e) {
            self::assertStringContainsString('Unexpected exception', $e->getMessage());
            $previous = $e->getPrevious();
            if (null === $previous) {
                self::fail('Expected a previous PSR-17 factory exception');
            }
            self::assertStringContainsString('PSR-17 response factory', $previous->getMessage());
        }
    }

    public function testSubscriptionArrayShape(): void
    {
        $factory = new WebPushClientFactory('pub', 'priv', '');
        $payload = $factory->subscriptionArray(
            'https://push.example/endpoint',
            'p256',
            'auth',
            'aesgcm',
        );

        self::assertSame(
            [
                'endpoint' => 'https://push.example/endpoint',
                'keys' => ['p256dh' => 'p256', 'auth' => 'auth'],
                'contentEncoding' => 'aesgcm',
            ],
            $payload,
        );
    }

    public function testCreateSubscriptionBuildsWebPushSubscription(): void
    {
        $factory = new WebPushClientFactory('pub', 'priv', '');
        $subscription = $factory->createSubscription(
            'https://push.example/endpoint',
            'p256dh-key',
            'auth-token',
            'aesgcm',
        );

        self::assertSame('https://push.example/endpoint', $subscription->getEndpoint());
    }
}
