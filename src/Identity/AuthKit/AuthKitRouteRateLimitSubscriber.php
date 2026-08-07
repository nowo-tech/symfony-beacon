<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/** IP rate limit for AuthKit POST routes (skipped in the test environment). */
abstract readonly class AuthKitRouteRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory $limiterFactory,
        private string $environment,
        private string $routeName,
        private string $tooManyRequestsMessage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 5]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || 'test' === $this->environment) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod(Request::METHOD_POST)) {
            return;
        }

        $route = (string) $request->attributes->get('_route');
        $base = str_ends_with($route, '_unlocalized')
            ? substr($route, 0, -\strlen('_unlocalized'))
            : $route;
        if ($this->routeName !== $base) {
            return;
        }

        $limiter = $this->limiterFactory->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(null, $this->tooManyRequestsMessage);
        }
    }
}
