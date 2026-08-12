<?php

declare(strict_types=1);

namespace App\Ingest\EventSubscriber;

use App\Ingest\Service\IngestIpRateLimiter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pre-auth IP rate limit for Envelope / OTLP ingest paths (before DB credential lookups).
 */
final readonly class IngestIpRateLimitSubscriber
{
    public function __construct(
        private IngestIpRateLimiter $ingestIpRateLimiter,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 32)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->ingestIpRateLimiter->isEnabled()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (1 !== preg_match('#^/api/[^/]+/(envelope|otlp)(/|$)#', $path)) {
            return;
        }

        $ip = $event->getRequest()->getClientIp() ?? '';
        if ($this->ingestIpRateLimiter->accept($ip)) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['message' => 'rate limit exceeded'],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => '60'],
        ));
    }
}
