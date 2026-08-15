<?php

declare(strict_types=1);

namespace App\Setup;

use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Nowo\SiteBackupBundle\Setup\DurableSetupDoneStoreInterface;
use Throwable;

/**
 * Durable setup-done signal via {@see InstanceSettings::setup_completed_at}.
 *
 * Wired as the {@see DurableSetupDoneStoreInterface} alias for SiteBackup ≥ 1.12.
 */
final readonly class InstanceSettingsDurableSetupDoneStore implements DurableSetupDoneStoreInterface
{
    public function __construct(
        private InstanceSettingsRepository $settingsRepository,
    ) {
    }

    public function isDone(): bool
    {
        try {
            return $this->settingsRepository->getOrCreate()->isSetupCompleted();
        } catch (Throwable) {
            return false;
        }
    }

    public function markDone(): void
    {
        try {
            $settings = $this->settingsRepository->getOrCreate();
            if ($settings->isSetupCompleted()) {
                return;
            }
            $settings->markSetupCompleted();
            $this->settingsRepository->save($settings);
        } catch (Throwable) {
            // Cold start / missing schema — SiteBackup finish will retry via SetupCompletedEvent.
        }
    }
}
