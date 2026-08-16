<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keep PWA bootstrap endpoints from minting / rotating session cookies.
 *
 * Browsers often fetch {@code /manifest.webmanifest} (and sometimes {@code /sw.js}) without
 * sending the authenticated session cookie. If Symfony starts a guest session and returns
 * {@code Set-Cookie}, the browser overwrites the logged-in session cookie and the next
 * navigation looks like a spontaneous logout.
 */
final class PwaStatelessCookieSubscriber
{
    private const string PATH_PATTERN = '#^/(manifest\.webmanifest|sw\.js)$#';

    /**
     * After SessionListener / CSRF / SecurityDataCollector / WebProfiler toolbar inject
     * (toolbar uses priority -2048).
     */
    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -3072)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (1 !== preg_match(self::PATH_PATTERN, $event->getRequest()->getPathInfo())) {
            return;
        }

        $headers = $event->getResponse()->headers;
        foreach ($headers->getCookies() as $cookie) {
            $headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }
        $headers->remove('Set-Cookie');
    }
}
