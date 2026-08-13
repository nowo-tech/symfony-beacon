<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\EventSubscriber;

use App\Notifications\EventSubscriber\HookIpRateLimitSubscriber;
use App\Notifications\Service\HookIpRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class HookIpRateLimitSubscriberTest extends TestCase
{
    public function testSkipsDisabledAndNonHookPaths(): void
    {
        $subscriber = new HookIpRateLimitSubscriber(new HookIpRateLimiter(new ArrayAdapter(), 0));
        $event = $this->event('/hooks/slack');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());

        $subscriber = new HookIpRateLimitSubscriber(new HookIpRateLimiter(new ArrayAdapter(), 3));
        $event = $this->event('/hooks/teams/assign');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testBlocksPublicHookStorms(): void
    {
        $subscriber = new HookIpRateLimitSubscriber(new HookIpRateLimiter(new ArrayAdapter(), 1));
        $subscriber->onKernelRequest($this->event('/hooks/email'));
        $blocked = $this->event('/hooks/teams/actions');
        $subscriber->onKernelRequest($blocked);

        self::assertSame(429, $blocked->getResponse()?->getStatusCode());
    }

    private function event(string $path): RequestEvent
    {
        $request = Request::create($path);
        $request->server->set('REMOTE_ADDR', '192.0.2.44');

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
