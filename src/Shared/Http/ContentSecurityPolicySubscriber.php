<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the baseline Content-Security-Policy on HTML responses.
 *
 * Lives in PHP (not Caddy) so Symfony WebProfiler can merge its script/style
 * nonces into the same header. Caddy still sets the other hardening headers.
 *
 * Swagger UI needs script-src 'unsafe-eval' (JSON Schema compile).
 * Debug / Web Debug Toolbar also needs 'unsafe-eval' because toolbar_js.html.twig
 * eval()s scripts from the /_wdt AJAX fragment.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -100)]
final readonly class ContentSecurityPolicySubscriber
{
    private const string CSP_PREFIX = "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; ";

    private const string CSP_SUFFIX = "; connect-src 'self' ws: wss:; worker-src 'self'; manifest-src 'self'";

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $kernelDebug,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if ($this->isProfilerFragmentPath($path)) {
            return;
        }

        $response = $event->getResponse();
        if ($response->headers->has('Content-Security-Policy')) {
            return;
        }

        if (!$this->isHtmlResponse($response)) {
            return;
        }

        $response->headers->set('Content-Security-Policy', $this->buildCsp($path));
    }

    private function buildCsp(string $path): string
    {
        $scriptSrc = "script-src 'self'";
        if ($this->isSwaggerUiPath($path) || $this->kernelDebug) {
            // Swagger: JSON Schema compile. Debug: WDT toolbar uses eval() on /_wdt HTML.
            $scriptSrc .= " 'unsafe-eval'";
        }

        return self::CSP_PREFIX.$scriptSrc.self::CSP_SUFFIX;
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html')) {
            return true;
        }

        // Symfony often omits Content-Type until the kernel finishes; sniff body.
        $content = $response->getContent();

        return \is_string($content) && str_contains(substr($content, 0, 512), '<html');
    }

    private function isSwaggerUiPath(string $path): bool
    {
        return '/api/doc' === $path || str_starts_with($path, '/api/doc/');
    }

    private function isProfilerFragmentPath(string $path): bool
    {
        return str_starts_with($path, '/_wdt') || str_starts_with($path, '/_profiler');
    }
}
