<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\ContentSecurityPolicySubscriber;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class ContentSecurityPolicySubscriberTest extends TestCase
{
    public function testProdDefaultCspHasNonceStyleSrcElemWithoutUnsafeInlineOnElements(): void
    {
        $response = $this->dispatch('/', '<html><body>ok</body></html>', kernelDebug: false);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("script-src 'self'", $csp);
        self::assertStringNotContainsString('unsafe-eval', $csp);
        self::assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $csp);
        self::assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp);
        self::assertMatchesRegularExpression("/style-src-elem 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp);
        self::assertDoesNotMatchRegularExpression("/style-src-elem[^;]*'unsafe-inline'/", $csp);
        self::assertStringContainsString("style-src-attr 'unsafe-inline'", $csp);
        self::assertStringContainsString("connect-src 'self' ws: wss:", $csp);
    }

    public function testDebugCspAllowsUnsafeEvalAndUnsafeInlineStyleElements(): void
    {
        $response = $this->dispatch('/', '<html><body>ok</body></html>', kernelDebug: true);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("script-src 'self' 'nonce-", $csp);
        self::assertStringContainsString("'unsafe-eval'", $csp);
        self::assertMatchesRegularExpression("/style-src-elem 'self' 'nonce-[A-Za-z0-9+\/=]+' 'unsafe-inline'/", $csp);
        self::assertStringContainsString("style-src-attr 'unsafe-inline'", $csp);
    }

    public function testSwaggerPathAllowsUnsafeEval(): void
    {
        $response = $this->dispatch('/admin/api/doc', '<html><body>doc</body></html>', kernelDebug: false);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("'unsafe-eval'", $csp);
    }

    public function testConnectSrcAddsCrossOriginMercureHub(): void
    {
        $response = $this->dispatch(
            '/',
            '<html><body>ok</body></html>',
            kernelDebug: false,
            mercurePublicUrl: 'https://hub.example:9443/.well-known/mercure',
            requestUri: 'https://app.example/',
        );
        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString('https://hub.example:9443', $csp);
    }

    public function testConnectSrcSkipsSameOriginMercureHub(): void
    {
        $response = $this->dispatch(
            '/',
            '<html><body>ok</body></html>',
            kernelDebug: false,
            mercurePublicUrl: 'https://localhost:9447/.well-known/mercure',
            requestUri: 'https://localhost:9447/menus/',
        );
        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("connect-src 'self' ws: wss:", $csp);
        self::assertStringNotContainsString('https://localhost:9447', $csp);
    }

    public function testDoesNotOverrideExistingCsp(): void
    {
        $response = new Response('<html></html>', Response::HTTP_OK, [
            'Content-Type' => 'text/html',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
        $kernel = $this->createStub(KernelInterface::class);
        $request = Request::create('/');
        $subscriber = new ContentSecurityPolicySubscriber(kernelDebug: false);
        $subscriber(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber($event);
        self::assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function testSkipsProfilerFragmentPaths(): void
    {
        $response = $this->dispatch('/_wdt/abcdef', '<div class="sf-toolbarreset"></div>', kernelDebug: true);
        self::assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function testRequestStoresNonceAttribute(): void
    {
        $kernel = $this->createStub(KernelInterface::class);
        $request = Request::create('/');
        $subscriber = new ContentSecurityPolicySubscriber(kernelDebug: false);
        $subscriber(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $nonce = $request->attributes->get(ContentSecurityPolicySubscriber::REQUEST_ATTR_NONCE);
        self::assertIsString($nonce);
        self::assertNotSame('', $nonce);
    }

    public function testOriginOfRejectsMalformedAndNonHttpUrls(): void
    {
        $method = new ReflectionMethod(ContentSecurityPolicySubscriber::class, 'originOf');
        $subscriber = new ContentSecurityPolicySubscriber(kernelDebug: false);

        self::assertNull($method->invoke($subscriber, 'http://example.com:99999'));
        self::assertNull($method->invoke($subscriber, 'ftp://example.test/resource'));
    }

    private function dispatch(
        string $path,
        string $html,
        bool $kernelDebug,
        string $mercurePublicUrl = '',
        string $requestUri = '',
    ): Response {
        $kernel = $this->createStub(KernelInterface::class);
        $request = '' !== $requestUri
            ? Request::create($requestUri)
            : Request::create($path);
        $response = new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
        $subscriber = new ContentSecurityPolicySubscriber($kernelDebug, $mercurePublicUrl);
        $subscriber(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $subscriber(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        return $response;
    }
}
