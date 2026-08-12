<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the baseline Content-Security-Policy on HTML responses.
 *
 * Lives in PHP (not Caddy) so Symfony WebProfiler can merge its script/style
 * nonces into the same header. Caddy still sets the other hardening headers.
 *
 * A per-request nonce is issued on the main request ({@see REQUEST_ATTR_NONCE}) for
 * host {@code <style>} blocks. Production uses {@code style-src-elem 'self' 'nonce-…'}
 * (no {@code unsafe-inline} on elements). {@code style-src-attr 'unsafe-inline'} allows
 * CSSOM ({@code element.style}) used by SortableJS, sidebar motion, etc. — a nonce on
 * {@code style-src} would ignore {@code unsafe-inline} and block those writes.
 *
 * Debug keeps {@code unsafe-inline} on {@code style-src-elem} for the Web Profiler.
 *
 * Swagger UI needs script-src 'unsafe-eval' (JSON Schema compile).
 * Debug / Web Debug Toolbar also needs 'unsafe-eval' because toolbar_js.html.twig
 * eval()s scripts from the /_wdt AJAX fragment.
 *
 * {@code connect-src} always includes {@code 'self' ws: wss:} plus the origin of
 * {@see $mercurePublicUrl} when that hub is absolute and cross-origin (EventSource).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -100)]
final readonly class ContentSecurityPolicySubscriber
{
    public const string REQUEST_ATTR_NONCE = '_beacon_csp_nonce';

    private const string CSP_BASE = "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; ";

    private const string CSP_TAIL = "; worker-src 'self'; manifest-src 'self'";

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $kernelDebug,
        #[Autowire('%beacon.mercure.env_public_url%')]
        private string $mercurePublicUrl = '',
    ) {
    }

    public function __invoke(RequestEvent|ResponseEvent $event): void
    {
        if ($event instanceof RequestEvent) {
            $this->onRequest($event);

            return;
        }

        $this->onResponse($event);
    }

    private function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->attributes->has(self::REQUEST_ATTR_NONCE)) {
            $request->attributes->set(self::REQUEST_ATTR_NONCE, base64_encode(random_bytes(16)));
        }
    }

    private function onResponse(ResponseEvent $event): void
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

        $response->headers->set('Content-Security-Policy', $this->buildCsp($path, $request));
    }

    private function buildCsp(string $path, Request $request): string
    {
        $nonce = (string) $request->attributes->get(self::REQUEST_ATTR_NONCE, '');
        // style-src-elem: <style> / stylesheets. style-src-attr: style="" + CSSOM.
        $styleSrcElem = "style-src-elem 'self'";
        if ('' !== $nonce) {
            $styleSrcElem .= " 'nonce-".$nonce."'";
        }
        if ($this->kernelDebug) {
            // Web Profiler injects <style> without our nonce; its listener also merges its own.
            $styleSrcElem .= " 'unsafe-inline'";
        }

        $styleSrcAttr = "style-src-attr 'unsafe-inline'";

        $scriptSrc = "script-src 'self'";
        if ('' !== $nonce) {
            // Lets host JSON-island boot scripts and Profiler-safe inline tags use the same nonce.
            $scriptSrc .= " 'nonce-".$nonce."'";
        }
        if ($this->isSwaggerUiPath($path) || $this->kernelDebug) {
            // Swagger: JSON Schema compile. Debug: WDT toolbar uses eval() on /_wdt HTML.
            $scriptSrc .= " 'unsafe-eval'";
        }

        $connectSrc = $this->buildConnectSrc($request);

        return self::CSP_BASE.$styleSrcElem.'; '.$styleSrcAttr.'; '.$scriptSrc.'; '.$connectSrc.self::CSP_TAIL;
    }

    private function buildConnectSrc(Request $request): string
    {
        $sources = ["'self'", 'ws:', 'wss:'];
        $mercureOrigin = $this->originOf($this->mercurePublicUrl);
        if (null !== $mercureOrigin && $mercureOrigin !== $request->getSchemeAndHttpHost()) {
            $sources[] = $mercureOrigin;
        }

        return 'connect-src '.implode(' ', $sources);
    }

    private function originOf(string $url): ?string
    {
        $trimmed = trim($url);
        if ('' === $trimmed) {
            return null;
        }
        $parts = parse_url($trimmed);
        if (!\is_array($parts)) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = $parts['host'] ?? null;
        if (!\in_array($scheme, ['http', 'https'], true) || !\is_string($host) || '' === $host) {
            return null;
        }
        $origin = $scheme.'://'.$host;
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
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
        return '/admin/api/doc' === $path || str_starts_with($path, '/admin/api/doc/');
    }

    private function isProfilerFragmentPath(string $path): bool
    {
        return str_starts_with($path, '/_wdt') || str_starts_with($path, '/_profiler');
    }
}
