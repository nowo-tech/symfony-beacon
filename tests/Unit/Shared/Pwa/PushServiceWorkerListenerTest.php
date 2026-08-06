<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Pwa;

use App\Shared\Pwa\PushServiceWorkerListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class PushServiceWorkerListenerTest extends TestCase
{
    public function testAppendsPushHandlersToServiceWorkerResponse(): void
    {
        $response = $this->dispatch('nowo_pwa_service_worker', '/* base sw */', HttpKernelInterface::MAIN_REQUEST);

        self::assertStringContainsString('/* base sw */', (string) $response->getContent());
        self::assertStringContainsString('Beacon Web Push', (string) $response->getContent());
        self::assertStringContainsString("self.addEventListener('push'", (string) $response->getContent());
    }

    public function testSkipsSubRequests(): void
    {
        $response = $this->dispatch('nowo_pwa_service_worker', '/* base */', HttpKernelInterface::SUB_REQUEST);

        self::assertSame('/* base */', $response->getContent());
    }

    public function testSkipsOtherRoutes(): void
    {
        $response = $this->dispatch('dashboard', '/* not sw */', HttpKernelInterface::MAIN_REQUEST);

        self::assertSame('/* not sw */', $response->getContent());
    }

    public function testDoesNotDuplicateAppend(): void
    {
        $already = "/* sw */\n/* Beacon Web Push (appended) */\n";
        $response = $this->dispatch('nowo_pwa_service_worker', $already, HttpKernelInterface::MAIN_REQUEST);

        self::assertSame($already, $response->getContent());
    }

    private function dispatch(string $route, string $content, int $requestType): Response
    {
        $kernel = $this->createStub(KernelInterface::class);
        $request = Request::create('/sw.js');
        $request->attributes->set('_route', $route);
        $response = new Response($content);
        $event = new ResponseEvent($kernel, $request, $requestType, $response);
        (new PushServiceWorkerListener())($event);

        return $response;
    }
}
