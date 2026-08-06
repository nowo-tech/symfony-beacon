<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use App\Ops\Metrics\MetricsCollector;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Prometheus scrape endpoint (admin session or metrics token).
 *
 * When metrics require-token is enabled (Ops defaults), a non-empty metrics token
 * must be configured or the endpoint returns 503.
 */
final class MetricsController extends AbstractController
{
    public function __construct(
        private readonly MetricsCollector $collector,
        private readonly PrometheusTextFormatter $formatter,
        private readonly InstanceOpsDefaults $opsDefaults,
    ) {
    }

    #[Route('/metrics', name: 'beacon_metrics', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $metricsToken = $this->opsDefaults->metricsToken();
        if ($this->opsDefaults->metricsRequireToken() && '' === $metricsToken) {
            return new Response("metrics token not configured\n", Response::HTTP_SERVICE_UNAVAILABLE, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        if (!$this->isAuthorized($request, $metricsToken)) {
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

    private function isAuthorized(Request $request, string $metricsToken): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ('' === $metricsToken) {
            return false;
        }

        $provided = $this->extractToken($request);
        if (null === $provided || '' === $provided) {
            return false;
        }

        return hash_equals($metricsToken, $provided);
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
