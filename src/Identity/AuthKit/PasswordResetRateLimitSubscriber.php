<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/** IP rate limit for AuthKit password-reset request POSTs (skipped in the test environment). */
final readonly class PasswordResetRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.password_reset')]
        private RateLimiterFactory $passwordResetLimiter,
        #[Autowire('%kernel.environment%')]
        private string $environment = 'prod',
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
        if ('nowo_auth_kit_reset_password_request' !== $base) {
            return;
        }

        $limiter = $this->passwordResetLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Too many password-reset requests.');
        }
    }
}
