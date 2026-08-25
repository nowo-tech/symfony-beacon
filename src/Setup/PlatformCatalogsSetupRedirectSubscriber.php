<?php

declare(strict_types=1);

namespace App\Setup;

use App\Shared\Settings\Service\PlatformBootstrapState;
use Nowo\SiteBackupBundle\Routing\SetupPathPrefixResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

/**
 * Extra gate for empty platform catalogs when SiteBackup's own redirect has not run yet.
 *
 * Primary signal is {@see PlatformCatalogsSetupNeedDetector} (SiteBackup 1.8+).
 * This subscriber keeps Beacon-specific behaviour: authenticated non-admins are not
 * forced to setup (FR-006), and a completed wizard progress is cleared if catalogs
 * are wiped again.
 */
final readonly class PlatformCatalogsSetupRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PlatformBootstrapState $platformBootstrapState,
        private AuthorizationCheckerInterface $authorizationChecker,
        private SetupPathPrefixResolver $setupPathPrefixResolver,
        #[Autowire('%nowo.site_backup.setup.path_prefix%')]
        private string $setupPathPrefix = '/setup',
        #[Autowire('%app.setup.check_platform_catalogs%')]
        private bool $enabled = true,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After SiteBackup SetupRequestSubscriber (~30).
            KernelEvents::REQUEST => [['onKernelRequest', 4]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethodSafe() || $request->isXmlHttpRequest()) {
            return;
        }

        // Empty Accept (BrowserKit / some probes) still counts as navigational HTML.
        $accept = (string) $request->headers->get('Accept', '');
        if ('' !== $accept
            && !str_contains($accept, 'text/html')
            && !str_contains($accept, '*/*')) {
            return;
        }

        try {
            if (!$this->platformBootstrapState->needsPlatformSeed()) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $path = $request->getPathInfo();
        if ($this->isExcludedPath($path)) {
            return;
        }

        $route = $request->attributes->get('_route');
        if (\is_string($route) && $this->isExcludedRoute($route)) {
            return;
        }

        // Non-admins who are already signed in keep working; only gate guests + admins.
        if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')
            && !$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->setupPathPrefixResolver->resolve()));
    }

    private function isExcludedPath(string $path): bool
    {
        $base = rtrim($this->setupPathPrefix, '/') ?: '/setup';
        $prefixes = [
            $base,
            '/_site_backup',
            '/_maintenance',
            '/admin/maintenance',
            '/_wdt',
            '/_profiler',
            '/build/',
            '/assets/',
            '/api/',
            '/legal',
            '/locale/',
            '/cookie_consent',
            '/cookie-consent/',
            '/cookie-consent-config',
            '/admin/_routing',
            '/_error',
            '/health/',
            '/_device',
        ];
        foreach ($prefixes as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return (bool) preg_match(
            '#^/(en|es|de|nl|fr|it|pt)/(legal|setup)(/|$)#',
            $path,
        );
    }

    private function isExcludedRoute(string $route): bool
    {
        // AuthKit stays gated until catalogs/setup are complete (first user via wizard).
        return str_starts_with($route, 'legal_')
            || str_starts_with($route, 'health_')
            || str_starts_with($route, 'guest_locale_')
            || str_starts_with($route, 'nowo_site_backup_')
            || str_starts_with($route, 'nowo_maintenance_mode_');
    }
}
