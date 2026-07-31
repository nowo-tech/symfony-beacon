<?php

declare(strict_types=1);

namespace App\Setup;

use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Nowo\SiteBackupBundle\Event\SetupCompletedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Keeps {@see InstanceSettings} in sync when SiteBackupBundle finishes a setup profile.
 */
#[AsEventListener(event: SetupCompletedEvent::class)]
final readonly class SetupCompletedSubscriber
{
    public function __construct(
        private InstanceSettingsRepository $settingsRepository,
    ) {
    }

    public function __invoke(): void
    {
        $settings = $this->settingsRepository->getOrCreate();
        if ($settings->isSetupCompleted()) {
            return;
        }
        $settings->markSetupCompleted();
        $this->settingsRepository->save($settings);
    }
}
