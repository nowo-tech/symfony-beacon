<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use App\Shared\Mailer\ConfiguredMailer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Hides AuthKit magic-login / password-reset until encrypted Mailer DSN can deliver mail.
 */
final class MailerGatedAuthKitRouteSubscriber implements EventSubscriberInterface
{
    private const array GATED_ROUTES = [
        'nowo_auth_kit_magic_login_request',
        'nowo_auth_kit_reset_password_request',
        'nowo_auth_kit_reset_password',
        'nowo_auth_kit_reset_password_code',
    ];

    public function __construct(
        private readonly ConfiguredMailer $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $defaultLocale = 'en',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 6]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (!\in_array($route, self::GATED_ROUTES, true)) {
            return;
        }

        if ($this->mailer->isMagicLoginAvailable()) {
            return;
        }

        $locale = $request->attributes->get('_locale');
        if (!\is_string($locale) || '' === $locale) {
            $locale = $this->defaultLocale;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('nowo_auth_kit_login', [
            '_locale' => $locale,
        ])));
    }
}
