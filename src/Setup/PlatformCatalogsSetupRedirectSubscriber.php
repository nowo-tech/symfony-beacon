<?php

declare(strict_types=1);

namespace App\Setup;

use App\Shared\Settings\Service\PlatformBootstrapState;
use Nowo\SiteBackupBundle\Model\SetupProgress;
use Nowo\SiteBackupBundle\Routing\SetupPathPrefixResolver;
use Nowo\SiteBackupBundle\Setup\SetupOrchestrator;
use Nowo\SiteBackupBundle\Setup\Storage\SetupMarkerManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

/**
 * When platform catalogs are missing, send HTML visitors to SiteBackup setup
 * (`/setup` or `/{_locale}/setup` depending on request locale / setup.locale).
 *
 * Complements SiteBackupBundle detectors (empty schema / markers). AuthKit, legal,
 * health, and ingest stay reachable so operators can still register or sign in.
 *
 * Also marks SiteBackup setup as required and clears a stale "completed" progress
 * so setup does not bounce back to `/` while catalogs are still empty.
 */
final readonly class PlatformCatalogsSetupRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PlatformBootstrapState $platformBootstrapState,
        private AuthorizationCheckerInterface $authorizationChecker,
        private SetupMarkerManager $setupMarkers,
        private SetupOrchestrator $setupOrchestrator,
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

        // Keep SiteBackup gate aligned with empty catalogs (avoids setup → / loops).
        if (!$this->setupMarkers->isRequiredMarked()) {
            $this->setupMarkers->markRequired('fresh_install');
        }
        if (SetupProgress::PHASE_COMPLETED === $this->setupOrchestrator->getProgress()->getPhase()) {
            $this->setupOrchestrator->resetProgress();
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
            '/_wdt',
            '/_profiler',
            '/build/',
            '/assets/',
            '/api/',
            '/login',
            '/register',
            '/logout',
            '/reset-password',
            '/legal',
            '/locale/',
            '/cookie_consent',
            '/cookie-consent/',
            '/_routing',
            '/_error',
            '/health/',
        ];
        foreach ($prefixes as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return (bool) preg_match(
            '#^/(en|es|de|nl|fr|it|pt)/(login|register|logout|reset-password|legal|setup)(/|$)#',
            $path,
        );
    }

    private function isExcludedRoute(string $route): bool
    {
        return str_starts_with($route, 'nowo_auth_kit_')
            || str_starts_with($route, 'legal_')
            || str_starts_with($route, 'health_')
            || str_starts_with($route, 'guest_locale_')
            || str_starts_with($route, 'nowo_site_backup_');
    }
}
