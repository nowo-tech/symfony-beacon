<?php

declare(strict_types=1);

namespace App\Shared\Settings\Service;

use App\Identity\Repository\UserRepository;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Who may open the first-run setup wizard.
 *
 * Anonymous bootstrap is allowed while the instance has no users yet
 * (chicken-and-egg before login), including when platform catalogs are empty
 * even if setup was marked complete. After the first account exists, only admins.
 */
final readonly class SetupWizardAccess
{
    public function __construct(
        private InstanceSettingsRepository $settingsRepository,
        private UserRepository $userRepository,
        private PlatformBootstrapState $platformBootstrapState,
        private Security $security,
    ) {
    }

    public function isBootstrapOpen(): bool
    {
        if (0 !== $this->userRepository->count([])) {
            return false;
        }

        if ($this->platformBootstrapState->needsPlatformSeed()) {
            return true;
        }

        return !$this->settingsRepository->getOrCreate()->isSetupCompleted();
    }

    public function canAccess(): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return $this->isBootstrapOpen();
    }
}
