<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Read\EventSubscriber;

use App\Api\Read\EventSubscriber\ReadApiRateLimitSubscriber;
use App\Api\Read\Service\ReadApiRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ReadApiRateLimitSubscriberTest extends TestCase
{
    public function testSkipsWhenDisabledOrWrongPath(): void
    {
        $subscriber = new ReadApiRateLimitSubscriber(new ReadApiRateLimiter(new ArrayAdapter(), 0));
        $event = $this->event('/api/projects/x/issues');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());

        $subscriber = new ReadApiRateLimitSubscriber(new ReadApiRateLimiter(new ArrayAdapter(), 1));
        $event = $this->event('/health/live');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testSetsTooManyRequestsWhenLimitExceeded(): void
    {
        $limiter = new ReadApiRateLimiter(new ArrayAdapter(), 1);
        $subscriber = new ReadApiRateLimitSubscriber($limiter);
        $first = $this->event('/api/projects/abc/issues');
        $subscriber->onKernelRequest($first);
        self::assertNull($first->getResponse());

        $second = $this->event('/api/projects/abc/issues');
        $subscriber->onKernelRequest($second);
        self::assertNotNull($second->getResponse());
        self::assertSame(429, $second->getResponse()->getStatusCode());
    }

    private function event(string $path): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create($path);
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
