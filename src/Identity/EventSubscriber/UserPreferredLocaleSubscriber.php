<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use App\Identity\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * For authenticated users, locale comes from the account preference (not URL _locale).
 * AuthKit routes keep locale_in_path (/en/login, /es/login, /de/login, …).
 *
 * Must run after the firewall (user available) and re-sync LocaleAware services
 * (Translator), because LocaleAwareListener already ran earlier with the default locale.
 */
final readonly class UserPreferredLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After the security firewall (8) so the user token is available.
            KernelEvents::REQUEST => [['onKernelRequest', 7]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof User) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (\is_string($route) && str_starts_with($route, 'nowo_auth_kit_')) {
            return;
        }

        // Drop ?_locale=… from app URLs — locale is account-driven here.
        if ($request->query->has('_locale')) {
            $qs = $request->query->all();
            unset($qs['_locale']);
            $target = $request->getPathInfo();
            if ([] !== $qs) {
                $target .= '?'.http_build_query($qs);
            }
            $event->setResponse(new RedirectResponse($target));

            return;
        }

        $preferred = $user->getPreferredLocale();
        if (null === $preferred || '' === $preferred) {
            return;
        }

        $request->setLocale($preferred);
        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $preferred);
        }

        // LocaleAwareListener (priority 15) already configured the translator from the
        // previous request locale / default — push the account preference now.
        if ($this->translator instanceof LocaleAwareInterface) {
            $this->translator->setLocale($preferred);
        }
    }
}
