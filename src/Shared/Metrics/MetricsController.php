<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Prometheus scrape endpoint (admin session or metrics token).
 *
 * When {@see $requireMetricsToken} is true (prod default), a non-empty
 * {@see $metricsToken} must be configured or the endpoint returns 503.
 */
final class MetricsController extends AbstractController
{
    public function __construct(
        private readonly MetricsCollector $collector,
        private readonly PrometheusTextFormatter $formatter,
        #[Autowire('%beacon.metrics_token%')]
        private readonly string $metricsToken = '',
        #[Autowire('%beacon.metrics_require_token%')]
        private readonly bool $requireMetricsToken = false,
    ) {
    }

    #[Route('/metrics', name: 'beacon_metrics', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        if ($this->requireMetricsToken && '' === $this->metricsToken) {
            return new Response("metrics token not configured\n", Response::HTTP_SERVICE_UNAVAILABLE, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        if (!$this->isAuthorized($request)) {
            return new Response("unauthorized\n", Response::HTTP_UNAUTHORIZED, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'WWW-Authenticate' => 'Bearer realm="beacon-metrics"',
            ]);
        }

        $body = $this->formatter->format($this->collector->collect());

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function isAuthorized(Request $request): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ('' === $this->metricsToken) {
            return false;
        }

        $provided = $this->extractToken($request);
        if (null === $provided || '' === $provided) {
            return false;
        }

        return hash_equals($this->metricsToken, $provided);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (\is_string($header) && str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        // Query ?token= is rejected (proxy/access logs / Referer leakage); use Bearer only.

        return null;
    }
}
