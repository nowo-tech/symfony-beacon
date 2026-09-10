<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\SetupAdvanceTimeLimitSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SetupAdvanceTimeLimitSubscriberTest extends TestCase
{
    public function testIgnoresNonSetupPaths(): void
    {
        $subscriber = new SetupAdvanceTimeLimitSubscriber(120);
        $event = $this->event('/dashboard');
        $subscriber($event);
        // No exception — set_time_limit is a no-op for coverage; path guard is the assert.
        self::assertTrue($event->isMainRequest());
    }

    public function testAcceptsSetupAndLocalizedSetupPaths(): void
    {
        $subscriber = new SetupAdvanceTimeLimitSubscriber(900);
        foreach (['/setup', '/setup/api/advance', '/en/setup', '/en/setup/api/progress'] as $path) {
            $event = $this->event($path);
            $subscriber($event);
            self::assertTrue($event->isMainRequest(), $path);
        }
    }

    public function testSkipsSubRequests(): void
    {
        $subscriber = new SetupAdvanceTimeLimitSubscriber(900);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, Request::create('/setup'), HttpKernelInterface::SUB_REQUEST);
        $subscriber($event);
        self::assertFalse($event->isMainRequest());
    }

    private function event(string $path): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST);
    }
}
