<?php

declare(strict_types=1);

namespace App\Shared\Mercure;

use App\Shared\Settings\Repository\InstanceSettingsRepository;

/**
 * Enables Mercure with Compose/env defaults when sample telemetry is loaded.
 *
 * Does not overwrite URL / public URL / JWT already stored in instance settings.
 */
final readonly class MercureSampleSeeder
{
    public function __construct(
        private InstanceSettingsRepository $settingsRepository,
        private ConfiguredMercure $configuredMercure,
        private string $envUrl,
        private string $envPublicUrl,
        private string $envJwtSecret,
    ) {
    }

    /**
     * @return bool true when settings were changed
     */
    public function seedDefaults(): bool
    {
        $settings = $this->settingsRepository->getOrCreate();
        $changed = false;

        if (!$settings->isMercureEnabled()) {
            $settings->setMercureEnabled(true);
            $changed = true;
        }

        $envUrl = trim($this->envUrl);
        if (
            (null === $settings->getMercureUrl() || '' === trim($settings->getMercureUrl()))
            && '' !== $envUrl
        ) {
            $settings->setMercureUrl($envUrl);
            $changed = true;
        }

        $envPublic = trim($this->envPublicUrl);
        if (
            (null === $settings->getMercurePublicUrl() || '' === trim($settings->getMercurePublicUrl()))
            && '' !== $envPublic
        ) {
            $settings->setMercurePublicUrl($envPublic);
            $changed = true;
        }

        $envSecret = trim($this->envJwtSecret);
        if (!$settings->hasMercureJwtSecret() && '' !== $envSecret) {
            $settings->setMercureJwtSecret($envSecret);
            $changed = true;
        }

        if ($changed) {
            $this->settingsRepository->save($settings);
            $this->configuredMercure->reset();
        }

        return $changed;
    }
}
