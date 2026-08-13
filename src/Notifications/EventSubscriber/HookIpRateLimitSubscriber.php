<?php

declare(strict_types=1);

namespace App\Notifications\EventSubscriber;

use App\Notifications\Service\HookIpRateLimiter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pre-auth IP rate limit for public Slack / Teams actions / inbound-email hooks.
 *
 * Excludes Teams Assign-me (session + ROLE_USER).
 */
final readonly class HookIpRateLimitSubscriber
{
    public function __construct(
        private HookIpRateLimiter $hookIpRateLimiter,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 32)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->hookIpRateLimiter->isEnabled()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (1 !== preg_match('#^/hooks/(slack|teams/actions|email)(/|$)#', $path)) {
            return;
        }

        $ip = $event->getRequest()->getClientIp() ?? '';
        if ($this->hookIpRateLimiter->accept($ip)) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['message' => 'rate limit exceeded'],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => '60'],
        ));
    }
}
