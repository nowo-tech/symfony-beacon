<?php

declare(strict_types=1);

namespace App\Api\Read\EventSubscriber;

use App\Api\Read\Service\ReadApiRateLimiter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * IP rate limit for Bearer Read API paths under {@code /api/projects/}.
 */
final readonly class ReadApiRateLimitSubscriber
{
    public function __construct(
        private ReadApiRateLimiter $readApiRateLimiter,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 32)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->readApiRateLimiter->isEnabled()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (1 !== preg_match('#^/api/projects/#', $path)) {
            return;
        }

        $ip = $event->getRequest()->getClientIp() ?? '';
        if ($this->readApiRateLimiter->accept($ip)) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['error' => 'rate_limit_exceeded'],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => '60'],
        ));
    }
}
