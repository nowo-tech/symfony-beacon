<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\AuthKit;

use App\Identity\AuthKit\MagicLoginRateLimitSubscriber;
use App\Identity\AuthKit\PasswordResetRateLimitSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class AuthKitRateLimitSubscriberTest extends TestCase
{
    public function testMagicLoginSubscribesAtPriorityFive(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 5]],
            MagicLoginRateLimitSubscriber::getSubscribedEvents(),
        );
    }

    public function testPasswordResetSubscribesAtPriorityFive(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 5]],
            PasswordResetRateLimitSubscriber::getSubscribedEvents(),
        );
    }

    public function testMagicLoginSkippedInTestEnvironment(): void
    {
        $subscriber = new MagicLoginRateLimitSubscriber($this->factory(limit: 1), 'test');
        $subscriber->onKernelRequest($this->event('nowo_auth_kit_magic_login_request', 'POST'));
        $this->addToAssertionCount(1);
    }

    public function testMagicLoginIgnoresGet(): void
    {
        $subscriber = new MagicLoginRateLimitSubscriber($this->factory(limit: 1), 'prod');
        $subscriber->onKernelRequest($this->event('nowo_auth_kit_magic_login_request', 'GET'));
        $this->addToAssertionCount(1);
    }

    public function testMagicLoginThrowsWhenLimitExceeded(): void
    {
        $factory = $this->factory(limit: 1);
        $subscriber = new MagicLoginRateLimitSubscriber($factory, 'prod');

        $subscriber->onKernelRequest($this->event('nowo_auth_kit_magic_login_request_unlocalized', 'POST'));

        $this->expectException(TooManyRequestsHttpException::class);
        $subscriber->onKernelRequest($this->event('nowo_auth_kit_magic_login_request', 'POST'));
    }

    public function testPasswordResetThrowsWhenLimitExceeded(): void
    {
        $factory = $this->factory(limit: 1);
        $subscriber = new PasswordResetRateLimitSubscriber($factory, 'prod');

        $subscriber->onKernelRequest($this->event('nowo_auth_kit_reset_password_request', 'POST'));

        $this->expectException(TooManyRequestsHttpException::class);
        $subscriber->onKernelRequest($this->event('nowo_auth_kit_reset_password_request', 'POST'));
    }

    private function factory(int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => 'unit-test-limiter',
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => '1 hour',
        ], new InMemoryStorage());
    }

    private function event(string $route, string $method): RequestEvent
    {
        $request = Request::create('/auth', $method, server: ['REMOTE_ADDR' => '203.0.113.50']);
        $request->attributes->set('_route', $route);

        return new RequestEvent(
            $this->createStub(KernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
