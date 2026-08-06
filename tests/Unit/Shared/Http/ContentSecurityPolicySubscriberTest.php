<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\ContentSecurityPolicySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class ContentSecurityPolicySubscriberTest extends TestCase
{
    public function testProdDefaultCspHasNoUnsafeEval(): void
    {
        $response = $this->dispatch('/', '<html><body>ok</body></html>', kernelDebug: false);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("script-src 'self'", $csp);
        self::assertStringNotContainsString('unsafe-eval', $csp);
        self::assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    public function testDebugCspAllowsUnsafeEvalForWebDebugToolbar(): void
    {
        $response = $this->dispatch('/', '<html><body>ok</body></html>', kernelDebug: true);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("script-src 'self' 'unsafe-eval'", $csp);
    }

    public function testSwaggerPathAllowsUnsafeEval(): void
    {
        $response = $this->dispatch('/admin/api/doc', '<html><body>doc</body></html>', kernelDebug: false);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("'unsafe-eval'", $csp);
    }

    public function testDoesNotOverrideExistingCsp(): void
    {
        $response = new Response('<html></html>', Response::HTTP_OK, [
            'Content-Type' => 'text/html',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
        $kernel = $this->createStub(KernelInterface::class);
        $event = new ResponseEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $response);
        (new ContentSecurityPolicySubscriber(kernelDebug: false))($event);
        self::assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function testSkipsProfilerFragmentPaths(): void
    {
        $response = $this->dispatch('/_wdt/abcdef', '<div class="sf-toolbarreset"></div>', kernelDebug: true);
        self::assertNull($response->headers->get('Content-Security-Policy'));
    }

    private function dispatch(string $path, string $html, bool $kernelDebug): Response
    {
        $kernel = $this->createStub(KernelInterface::class);
        $response = new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
        $event = new ResponseEvent($kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST, $response);
        (new ContentSecurityPolicySubscriber($kernelDebug))($event);

        return $response;
    }
}
