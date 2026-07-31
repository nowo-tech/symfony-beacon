<?php

declare(strict_types=1);

namespace App\Shared\Settings\Service;

use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use App\Shared\Appearance\SiteAppearanceProvider;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Export/import allowlisted non-secret instance settings (044).
 *
 * Secrets (Mailer DSN/From, Mercure URLs/JWT) are never written to export JSON
 * and are rejected on import.
 */
final class InstanceConfigPortability
{
    public const string SCHEMA = 'beacon-instance-config';
    public const int VERSION = 1;

    /** @var list<string> */
    private const array FORBIDDEN_KEYS = [
        'mailer_dsn',
        'mailer_from',
        'mercure_url',
        'mercure_public_url',
        'mercure_jwt_secret',
        'client_secret',
        'client_id',
        'password',
        'secret',
        'dsn',
    ];

    public function __construct(
        private readonly SiteAppearanceRepository $appearanceRepository,
        private readonly InstanceSettingsRepository $instanceSettingsRepository,
        private readonly SiteAppearanceProvider $appearanceProvider,
    ) {
    }

    /**
     * @return array{
     *     schema: string,
     *     version: int,
     *     exported_at: string,
     *     appearance: array<string, string>,
     *     instance: array<string, bool|string|null>
     * }
     */
    public function export(): array
    {
        $appearance = $this->appearanceRepository->getOrCreate();
        $settings = $this->instanceSettingsRepository->getOrCreate();

        return [
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'exported_at' => (new DateTimeImmutable())->format(\DATE_ATOM),
            'appearance' => [
                'brand_name' => $appearance->getBrandName(),
                'brand_eyebrow' => $appearance->getBrandEyebrow(),
                'accent_color' => $appearance->getAccentColor(),
                'accent_deep_color' => $appearance->getAccentDeepColor(),
                'accent_color_dark' => $appearance->getAccentColorDark(),
                'accent_deep_color_dark' => $appearance->getAccentDeepColorDark(),
                'danger_color' => $appearance->getDangerColor(),
                'danger_color_dark' => $appearance->getDangerColorDark(),
                'warn_color' => $appearance->getWarnColor(),
                'warn_color_dark' => $appearance->getWarnColorDark(),
                'paper_color' => $appearance->getPaperColor(),
                'paper_color_dark' => $appearance->getPaperColorDark(),
                'ink_color' => $appearance->getInkColor(),
                'ink_color_dark' => $appearance->getInkColorDark(),
                'surface_color' => $appearance->getSurfaceColor(),
                'surface_color_dark' => $appearance->getSurfaceColorDark(),
            ],
            'instance' => [
                'mercure_enabled' => $settings->isMercureEnabled(),
                'setup_completed' => $settings->isSetupCompleted(),
                'setup_completed_at' => $settings->getSetupCompletedAt()?->format(\DATE_ATOM),
                'mailer_configured' => $settings->hasMailerDsn(),
                'mercure_url_configured' => null !== $settings->getMercureUrl() && '' !== $settings->getMercureUrl(),
                'mercure_public_url_configured' => null !== $settings->getMercurePublicUrl() && '' !== $settings->getMercurePublicUrl(),
                'mercure_jwt_configured' => $settings->hasMercureJwtSecret(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string> Applied section labels for flash/audit
     */
    public function import(array $payload): array
    {
        $this->assertNoForbiddenKeys($payload);

        if (($payload['schema'] ?? null) !== self::SCHEMA) {
            throw new InvalidArgumentException('invalid_schema');
        }
        if ((int) ($payload['version'] ?? 0) !== self::VERSION) {
            throw new InvalidArgumentException('unsupported_version');
        }

        $applied = [];

        if (isset($payload['appearance']) && \is_array($payload['appearance'])) {
            $this->applyAppearance($payload['appearance']);
            $applied[] = 'appearance';
        }

        if (isset($payload['instance']) && \is_array($payload['instance'])) {
            $this->applyInstanceFlags($payload['instance']);
            $applied[] = 'instance';
        }

        if ([] === $applied) {
            throw new InvalidArgumentException('empty_payload');
        }

        return $applied;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyAppearance(array $data): void
    {
        $appearance = $this->appearanceRepository->getOrCreate();

        if (isset($data['brand_name']) && \is_string($data['brand_name'])) {
            $appearance->setBrandName($data['brand_name']);
        }
        if (isset($data['brand_eyebrow']) && \is_string($data['brand_eyebrow'])) {
            $appearance->setBrandEyebrow($data['brand_eyebrow']);
        }

        $colorMap = [
            'accent_color' => 'setAccentColor',
            'accent_deep_color' => 'setAccentDeepColor',
            'accent_color_dark' => 'setAccentColorDark',
            'accent_deep_color_dark' => 'setAccentDeepColorDark',
            'danger_color' => 'setDangerColor',
            'danger_color_dark' => 'setDangerColorDark',
            'warn_color' => 'setWarnColor',
            'warn_color_dark' => 'setWarnColorDark',
            'paper_color' => 'setPaperColor',
            'paper_color_dark' => 'setPaperColorDark',
            'ink_color' => 'setInkColor',
            'ink_color_dark' => 'setInkColorDark',
            'surface_color' => 'setSurfaceColor',
            'surface_color_dark' => 'setSurfaceColorDark',
        ];
        foreach ($colorMap as $key => $setter) {
            if (!isset($data[$key]) || !\is_string($data[$key])) {
                continue;
            }
            $color = strtolower(trim($data[$key]));
            if (1 !== preg_match('/^#[0-9a-f]{6}$/', $color)) {
                throw new InvalidArgumentException('invalid_color:'.$key);
            }
            $appearance->{$setter}($color);
        }

        $this->appearanceRepository->save($appearance);
        $this->appearanceProvider->refresh();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyInstanceFlags(array $data): void
    {
        $settings = $this->instanceSettingsRepository->getOrCreate();

        // Metadata-only keys are ignored on import (mailer_configured, mercure_*_configured).
        if (\array_key_exists('mercure_enabled', $data)) {
            $settings->setMercureEnabled((bool) $data['mercure_enabled']);
        }
        if (\array_key_exists('setup_completed', $data)) {
            if ((bool) $data['setup_completed']) {
                if (!$settings->isSetupCompleted()) {
                    $settings->markSetupCompleted();
                }
            } else {
                $settings->clearSetupCompleted();
            }
        }

        $this->instanceSettingsRepository->save($settings);
    }

    /**
     * @param array<mixed> $node
     */
    private function assertNoForbiddenKeys(array $node): void
    {
        foreach ($node as $key => $value) {
            if (\is_string($key)) {
                $normalized = strtolower($key);
                if (\in_array($normalized, self::FORBIDDEN_KEYS, true)) {
                    throw new InvalidArgumentException('forbidden_key:'.$key);
                }
            }
            if (\is_array($value)) {
                $this->assertNoForbiddenKeys($value);
            }
        }
    }
}
