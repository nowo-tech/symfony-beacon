<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\AuthKit;

use App\Identity\AuthKit\MagicLoginRateLimitSubscriber;
use App\Identity\AuthKit\PasswordResetRateLimitSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

final class AuthKitRouteRateLimitSubscriberTest extends TestCase
{
    public function testSkipsInTestEnvironment(): void
    {
        $subscriber = new MagicLoginRateLimitSubscriber($this->factory(limit: 1), 'test');
        $event = $this->event(Request::create('/magic', Request::METHOD_POST), 'nowo_auth_kit_magic_login_request');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testSkipsNonPostAndWrongRoute(): void
    {
        $subscriber = new MagicLoginRateLimitSubscriber($this->factory(limit: 1), 'prod');
        $get = $this->event(Request::create('/magic', Request::METHOD_GET), 'nowo_auth_kit_magic_login_request');
        $subscriber->onKernelRequest($get);
        self::assertNull($get->getResponse());

        $wrong = $this->event(Request::create('/login', Request::METHOD_POST), 'nowo_auth_kit_login');
        $subscriber->onKernelRequest($wrong);
        self::assertNull($wrong->getResponse());
    }

    public function testThrowsWhenLimitExceededForLocalizedAndUnlocalizedRoutes(): void
    {
        $factory = $this->factory(limit: 1);
        $subscriber = new PasswordResetRateLimitSubscriber($factory, 'prod');

        $first = $this->event(
            Request::create('/reset', Request::METHOD_POST, server: ['REMOTE_ADDR' => '203.0.113.10']),
            'nowo_auth_kit_reset_password_request',
        );
        $subscriber->onKernelRequest($first);

        $this->expectException(TooManyRequestsHttpException::class);
        $this->expectExceptionMessage('Too many password-reset requests.');
        $second = $this->event(
            Request::create('/reset', Request::METHOD_POST, server: ['REMOTE_ADDR' => '203.0.113.10']),
            'nowo_auth_kit_reset_password_request_unlocalized',
        );
        $subscriber->onKernelRequest($second);
    }

    public function testMagicLoginSubscribedEvents(): void
    {
        self::assertArrayHasKey('kernel.request', MagicLoginRateLimitSubscriber::getSubscribedEvents());
    }

    private function factory(int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => 'authkit_test_'.uniqid('', true),
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => '1 minute',
        ], new CacheStorage(new ArrayAdapter()));
    }

    private function event(Request $request, string $route): RequestEvent
    {
        $request->attributes->set('_route', $route);
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
