<?php

declare(strict_types=1);

namespace App\Shared\Appearance;

/**
 * Appearance admin UI sections (route slugs + form field groups).
 */
enum AppearanceSettingsSection: string
{
    case Themes = 'themes';
    case Brand = 'brand';
    case Layout = 'layout';
    case Colors = 'colors';

    public function tabLabelKey(): string
    {
        return 'appearance.tab.'.$this->value;
    }

    public function helpKey(): string
    {
        return 'appearance.tab.'.$this->value.'_help';
    }

    /**
     * @return list<AppearanceSettingsSubtab>
     */
    public function subtabs(): array
    {
        return match ($this) {
            self::Themes => [
                AppearanceSettingsSubtab::Light,
                AppearanceSettingsSubtab::Dark,
            ],
            self::Colors => [
                AppearanceSettingsSubtab::Accents,
                AppearanceSettingsSubtab::Status,
                AppearanceSettingsSubtab::Surfaces,
            ],
            default => [],
        };
    }

    public function defaultSubtab(): ?AppearanceSettingsSubtab
    {
        $subs = $this->subtabs();

        return $subs[0] ?? null;
    }

    public static function routeRequirement(): string
    {
        return implode('|', array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        ));
    }
}
