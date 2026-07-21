<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use App\Identity\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Applies sticky session locale for anonymous requests without a path {_locale}.
 *
 * When the route already carries {_locale} (AuthKit / legal / setup), the path wins.
 * Authenticated users are handled by {@see UserPreferredLocaleSubscriber}.
 */
final readonly class GuestSessionLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private TranslatorInterface $translator,
        /** @var list<string> */
        private array $enabledLocales,
        private string $defaultLocale,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After LocaleListener (16) / LocaleAwareListener (15); before or with user preference (7).
            KernelEvents::REQUEST => [['onKernelRequest', 8]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof User) {
            return;
        }

        $request = $event->getRequest();

        // Path locale is authoritative on dual public routes (/{_locale}/login, /{_locale}/legal/…).
        $pathLocale = $request->attributes->get('_locale');
        if (\is_string($pathLocale) && '' !== $pathLocale) {
            return;
        }

        if (!$request->hasSession()) {
            return;
        }

        $sessionLocale = $request->getSession()->get('_locale');
        if (!\is_string($sessionLocale) || '' === $sessionLocale) {
            return;
        }

        $sessionLocale = strtolower($sessionLocale);
        if (!\in_array($sessionLocale, $this->enabledLocales, true)) {
            $sessionLocale = $this->defaultLocale;
        }

        $request->setLocale($sessionLocale);
        if ($this->translator instanceof LocaleAwareInterface) {
            $this->translator->setLocale($sessionLocale);
        }
    }
}
