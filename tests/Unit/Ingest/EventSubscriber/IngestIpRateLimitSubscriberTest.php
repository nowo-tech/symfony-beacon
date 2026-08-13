<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\EventSubscriber;

use App\Ingest\EventSubscriber\IngestIpRateLimitSubscriber;
use App\Ingest\Service\IngestIpRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class IngestIpRateLimitSubscriberTest extends TestCase
{
    public function testSkipsDisabledAndNonIngestPaths(): void
    {
        $subscriber = new IngestIpRateLimitSubscriber(new IngestIpRateLimiter(new ArrayAdapter(), 0));
        $event = $this->event('/api/p/envelope');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());

        $subscriber = new IngestIpRateLimitSubscriber(new IngestIpRateLimiter(new ArrayAdapter(), 5));
        $event = $this->event('/dashboard');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testBlocksWhenIpBudgetExhausted(): void
    {
        $subscriber = new IngestIpRateLimitSubscriber(new IngestIpRateLimiter(new ArrayAdapter(), 1));
        $subscriber->onKernelRequest($this->event('/api/demo/envelope'));
        $blocked = $this->event('/api/demo/otlp/v1/logs');
        $subscriber->onKernelRequest($blocked);

        self::assertSame(429, $blocked->getResponse()?->getStatusCode());
    }

    private function event(string $path): RequestEvent
    {
        $request = Request::create($path);
        $request->server->set('REMOTE_ADDR', '198.51.100.2');

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
