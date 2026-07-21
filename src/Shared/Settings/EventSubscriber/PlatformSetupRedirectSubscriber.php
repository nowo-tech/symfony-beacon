<?php

declare(strict_types=1);

namespace App\Shared\Settings\EventSubscriber;

use App\Shared\Locale\LocalizedPublicPath;
use App\Shared\Settings\Service\PlatformBootstrapState;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Forces browser HTML traffic to the setup wizard while platform catalogs are empty.
 *
 * Authenticated non-admins are not redirected (they cannot run setup); admins and
 * anonymous visitors are sent to the wizard until menus/breadcrumbs/cookies exist.
 */
final class PlatformSetupRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PlatformBootstrapState $platformBootstrapState,
        private readonly LocalizedPublicPath $localizedPublicPath,
        private readonly Security $security,
        private readonly string $defaultLocale = 'en',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After locale subscribers (7–8) and AuthKit mailer gate (6); route is already resolved.
        return [KernelEvents::REQUEST => ['onKernelRequest', 4]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->isBrowserHtmlGet($request)) {
            return;
        }

        $route = $request->attributes->get('_route');
        if (!\is_string($route) || '' === $route) {
            return;
        }

        if ($this->shouldSkipRoute($route, $request)) {
            return;
        }

        if (!$this->platformBootstrapState->needsPlatformSeed()) {
            return;
        }

        // Non-admin signed-in users cannot access setup — do not trap them in a 403 loop.
        if (null !== $this->security->getUser() && !$this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $locale = $request->attributes->get('_locale');
        if (!\is_string($locale) || '' === $locale) {
            $locale = $this->defaultLocale;
        }

        $event->setResponse(new RedirectResponse($this->localizedPublicPath->setupPath($locale)));
    }

    private function isBrowserHtmlGet(Request $request): bool
    {
        if (Request::METHOD_GET !== $request->getMethod()) {
            return false;
        }

        if ($request->isXmlHttpRequest()) {
            return false;
        }

        $accept = $request->headers->get('Accept', 'text/html');
        if (!str_contains($accept, 'text/html') && !str_contains($accept, '*/*')) {
            return false;
        }

        return true;
    }

    private function shouldSkipRoute(string $route, Request $request): bool
    {
        if (str_starts_with($route, 'setup_wizard')) {
            return true;
        }

        if (str_starts_with($route, 'health_')) {
            return true;
        }

        if (str_starts_with($route, 'nowo_auth_kit_')) {
            return true;
        }

        if (str_starts_with($route, 'legal_')) {
            return true;
        }

        if (str_starts_with($route, 'guest_locale_')) {
            return true;
        }

        if (str_starts_with($route, '_wdt') || str_starts_with($route, '_profiler') || str_starts_with($route, '_fragment')) {
            return true;
        }

        if (str_contains($route, 'cookie_consent') || str_contains($route, 'cookie-consent')) {
            return true;
        }

        $path = $request->getPathInfo();
        if (str_starts_with($path, '/api/') && str_contains($path, '/envelope')) {
            return true;
        }

        if (str_starts_with($path, '/build/') || str_starts_with($path, '/assets/')) {
            return true;
        }

        if (\in_array($path, ['/manifest.webmanifest', '/sw.js', '/offline'], true)) {
            return true;
        }

        return false;
    }
}
