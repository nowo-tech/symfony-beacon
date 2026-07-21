<?php

declare(strict_types=1);

namespace App\Shared\Settings\Service;

use App\Identity\Repository\UserRepository;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Who may open the first-run setup wizard.
 *
 * Anonymous bootstrap is allowed only while the instance has no users yet
 * (chicken-and-egg before login). After the first account exists, only admins.
 */
final readonly class SetupWizardAccess
{
    public function __construct(
        private InstanceSettingsRepository $settingsRepository,
        private UserRepository $userRepository,
        private Security $security,
    ) {
    }

    public function isBootstrapOpen(): bool
    {
        if ($this->settingsRepository->getOrCreate()->isSetupCompleted()) {
            return false;
        }

        return 0 === $this->userRepository->count([]);
    }

    public function canAccess(): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return $this->isBootstrapOpen();
    }
}
