<?php

declare(strict_types=1);

namespace App\Setup;

use App\Shared\Settings\Service\PlatformBootstrapState;
use Nowo\SiteBackupBundle\Attribute\AsSetupNeedDetector;
use Nowo\SiteBackupBundle\Setup\SetupNeedDetectorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

/**
 * SiteBackup setup-need detector: empty platform catalogs (menus / breadcrumbs / cookies).
 *
 * Prefer this over sticky {@code setup.required} markers (SiteBackup 1.8+).
 * Authenticated non-admins are not gated (Beacon FR-006).
 */
#[AsSetupNeedDetector(priority: 50)]
final readonly class PlatformCatalogsSetupNeedDetector implements SetupNeedDetectorInterface
{
    public function __construct(
        private PlatformBootstrapState $platformBootstrapState,
        private AuthorizationCheckerInterface $authorizationChecker,
        #[Autowire('%app.setup.check_platform_catalogs%')]
        private bool $enabled = true,
    ) {
    }

    public function isSetupRequired(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Match PlatformCatalogsSetupRedirectSubscriber: signed-in non-admins keep working.
        if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')
            && !$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return false;
        }

        try {
            return $this->platformBootstrapState->needsPlatformSeed();
        } catch (Throwable) {
            // Unknown / unreachable database ⇒ cold start (same signal as doctrine_connect).
            return true;
        }
    }

    public function getReason(): string
    {
        return 'platform catalogs missing';
    }
}
