<?php

declare(strict_types=1);

namespace App\Shared\Appearance;

use Deprecated;
use App\Shared\Appearance\Entity\SiteAppearance;

/**
 * Named appearance palettes applied independently for light vs dark mode.
 *
 * Light presets only overwrite light color fields; dark presets only overwrite
 * dark color fields, so each mode can stay selected on its own tab.
 */
final class AppearanceThemePresets
{
    public const string CUSTOM = 'custom';
    public const string BEACON = 'beacon';

    /**
     * @return list<array{
     *     id: string,
     *     mode: 'light'|'dark',
     *     accent: string,
     *     accentDeep: string,
     *     accentDark: string,
     *     accentDeepDark: string,
     *     danger: string,
     *     dangerDark: string,
     *     warn: string,
     *     warnDark: string,
     *     paper: string,
     *     paperDark: string,
     *     ink: string,
     *     inkDark: string,
     *     surface: string,
     *     surfaceDark: string
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'id' => self::BEACON,
                'mode' => 'light',
                'accent' => SiteAppearance::DEFAULT_ACCENT,
                'accentDeep' => SiteAppearance::DEFAULT_ACCENT_DEEP,
                'accentDark' => SiteAppearance::DEFAULT_ACCENT_DARK,
                'accentDeepDark' => SiteAppearance::DEFAULT_ACCENT_DEEP_DARK,
                'danger' => SiteAppearance::DEFAULT_DANGER,
                'dangerDark' => SiteAppearance::DEFAULT_DANGER_DARK,
                'warn' => SiteAppearance::DEFAULT_WARN,
                'warnDark' => SiteAppearance::DEFAULT_WARN_DARK,
                'paper' => SiteAppearance::DEFAULT_PAPER,
                'paperDark' => SiteAppearance::DEFAULT_PAPER_DARK,
                'ink' => SiteAppearance::DEFAULT_INK,
                'inkDark' => SiteAppearance::DEFAULT_INK_DARK,
                'surface' => SiteAppearance::DEFAULT_SURFACE,
                'surfaceDark' => SiteAppearance::DEFAULT_SURFACE_DARK,
            ],
            [
                'id' => 'ocean',
                'mode' => 'light',
                'accent' => '#0e7490',
                'accentDeep' => '#155e75',
                'accentDark' => '#22d3ee',
                'accentDeepDark' => '#67e8f9',
                'danger' => '#b42318',
                'dangerDark' => '#f97066',
                'warn' => '#b54708',
                'warnDark' => '#fdb022',
                'paper' => '#f0f9fb',
                'paperDark' => '#071318',
                'ink' => '#0c3a45',
                'inkDark' => '#e0f4f8',
                'surface' => '#ffffff',
                'surfaceDark' => '#0f1c22',
            ],
            [
                'id' => 'slate',
                'mode' => 'light',
                'accent' => '#475569',
                'accentDeep' => '#334155',
                'accentDark' => '#94a3b8',
                'accentDeepDark' => '#cbd5e1',
                'danger' => '#b42318',
                'dangerDark' => '#f97066',
                'warn' => '#b54708',
                'warnDark' => '#fdb022',
                'paper' => '#f1f5f9',
                'paperDark' => '#0b1220',
                'ink' => '#0f172a',
                'inkDark' => '#e2e8f0',
                'surface' => '#ffffff',
                'surfaceDark' => '#111827',
            ],
            [
                'id' => 'sandstone',
                'mode' => 'light',
                'accent' => '#9a6700',
                'accentDeep' => '#7a5200',
                'accentDark' => '#f5c84c',
                'accentDeepDark' => '#fde68a',
                'danger' => '#b42318',
                'dangerDark' => '#f97066',
                'warn' => '#b54708',
                'warnDark' => '#fdb022',
                'paper' => '#f7f4ef',
                'paperDark' => '#14110c',
                'ink' => '#2a2118',
                'inkDark' => '#f3ebe0',
                'surface' => '#fffcf7',
                'surfaceDark' => '#1c1812',
            ],
            [
                'id' => 'midnight',
                'mode' => 'dark',
                'accent' => '#1d4ed8',
                'accentDeep' => '#1e3a8a',
                'accentDark' => '#60a5fa',
                'accentDeepDark' => '#93c5fd',
                'danger' => '#b42318',
                'dangerDark' => '#f97066',
                'warn' => '#b54708',
                'warnDark' => '#fdb022',
                'paper' => '#eef2ff',
                'paperDark' => '#070b16',
                'ink' => '#1e1b4b',
                'inkDark' => '#e0e7ff',
                'surface' => '#ffffff',
                'surfaceDark' => '#0f1524',
            ],
            [
                'id' => 'obsidian',
                'mode' => 'dark',
                'accent' => '#059669',
                'accentDeep' => '#047857',
                'accentDark' => '#34d399',
                'accentDeepDark' => '#6ee7b7',
                'danger' => '#b42318',
                'dangerDark' => '#f97066',
                'warn' => '#b54708',
                'warnDark' => '#fdb022',
                'paper' => '#f3f4f6',
                'paperDark' => '#050505',
                'ink' => '#111827',
                'inkDark' => '#f3f4f6',
                'surface' => '#ffffff',
                'surfaceDark' => '#0a0a0a',
            ],
            [
                'id' => 'aurora',
                'mode' => 'dark',
                'accent' => '#0f766e',
                'accentDeep' => '#115e59',
                'accentDark' => '#2dd4bf',
                'accentDeepDark' => '#5eead4',
                'danger' => '#b42318',
                'dangerDark' => '#f97066',
                'warn' => '#b54708',
                'warnDark' => '#fdb022',
                'paper' => '#ecfdf5',
                'paperDark' => '#021411',
                'ink' => '#064e3b',
                'inkDark' => '#d1fae5',
                'surface' => '#ffffff',
                'surfaceDark' => '#0a1f1a',
            ],
            [
                'id' => 'ember',
                'mode' => 'dark',
                'accent' => '#c2410c',
                'accentDeep' => '#9a3412',
                'accentDark' => '#fb923c',
                'accentDeepDark' => '#fdba74',
                'danger' => '#b42318',
                'dangerDark' => '#f97066',
                'warn' => '#b54708',
                'warnDark' => '#fdb022',
                'paper' => '#fff7ed',
                'paperDark' => '#120a06',
                'ink' => '#431407',
                'inkDark' => '#ffedd5',
                'surface' => '#ffffff',
                'surfaceDark' => '#1a100c',
            ],
        ];
    }

    public static function has(string $id): bool
    {
        return null !== self::get($id);
    }

    /**
     * @return ?array{
     *     id: string,
     *     mode: 'light'|'dark',
     *     accent: string,
     *     accentDeep: string,
     *     accentDark: string,
     *     accentDeepDark: string,
     *     danger: string,
     *     dangerDark: string,
     *     warn: string,
     *     warnDark: string,
     *     paper: string,
     *     paperDark: string,
     *     ink: string,
     *     inkDark: string,
     *     surface: string,
     *     surfaceDark: string
     * }
     */
    public static function get(string $id): ?array
    {
        foreach (self::all() as $preset) {
            if ($preset['id'] === $id) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @return list<array{
     *     id: string,
     *     mode: 'light'|'dark',
     *     accent: string,
     *     accentDeep: string,
     *     accentDark: string,
     *     accentDeepDark: string,
     *     danger: string,
     *     dangerDark: string,
     *     warn: string,
     *     warnDark: string,
     *     paper: string,
     *     paperDark: string,
     *     ink: string,
     *     inkDark: string,
     *     surface: string,
     *     surfaceDark: string
     * }>
     */
    public static function byMode(string $mode): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $preset): bool => $preset['mode'] === $mode,
        ));
    }

    /**
     * Apply only the light or dark half of a preset (based on its mode tag).
     */
    public static function apply(SiteAppearance $appearance, string $id): bool
    {
        $preset = self::get($id);
        if (null === $preset) {
            return false;
        }

        if ('dark' === $preset['mode']) {
            $appearance
                ->setAccentColorDark($preset['accentDark'])
                ->setAccentDeepColorDark($preset['accentDeepDark'])
                ->setDangerColorDark($preset['dangerDark'])
                ->setWarnColorDark($preset['warnDark'])
                ->setPaperColorDark($preset['paperDark'])
                ->setInkColorDark($preset['inkDark'])
                ->setSurfaceColorDark($preset['surfaceDark'])
                ->setThemeIdDark($preset['id']);

            return true;
        }

        $appearance
            ->setAccentColor($preset['accent'])
            ->setAccentDeepColor($preset['accentDeep'])
            ->setDangerColor($preset['danger'])
            ->setWarnColor($preset['warn'])
            ->setPaperColor($preset['paper'])
            ->setInkColor($preset['ink'])
            ->setSurfaceColor($preset['surface'])
            ->setThemeId($preset['id']);

        return true;
    }

    #[Deprecated(message: 'use matchLightId() / matchDarkId() for independent modes')]
    public static function matchId(SiteAppearance $appearance): string
    {
        $light = self::matchLightId($appearance);
        if (self::CUSTOM !== $light) {
            return $light;
        }

        return self::matchDarkId($appearance);
    }

    public static function matchLightId(SiteAppearance $appearance): string
    {
        foreach (self::byMode('light') as $preset) {
            if (self::lightColorsMatch($appearance, $preset)) {
                return $preset['id'];
            }
        }

        return self::CUSTOM;
    }

    public static function matchDarkId(SiteAppearance $appearance): string
    {
        foreach (self::byMode('dark') as $preset) {
            if (self::darkColorsMatch($appearance, $preset)) {
                return $preset['id'];
            }
        }

        return self::CUSTOM;
    }

    public static function matchForMode(SiteAppearance $appearance, string $mode): string
    {
        return 'dark' === $mode
            ? self::matchDarkId($appearance)
            : self::matchLightId($appearance);
    }

    /**
     * @param array{
     *     accent: string,
     *     accentDeep: string,
     *     danger: string,
     *     warn: string,
     *     paper: string,
     *     ink: string,
     *     surface: string
     * } $preset
     */
    private static function lightColorsMatch(SiteAppearance $appearance, array $preset): bool
    {
        return $appearance->getAccentColor() === $preset['accent']
            && $appearance->getAccentDeepColor() === $preset['accentDeep']
            && $appearance->getDangerColor() === $preset['danger']
            && $appearance->getWarnColor() === $preset['warn']
            && $appearance->getPaperColor() === $preset['paper']
            && $appearance->getInkColor() === $preset['ink']
            && $appearance->getSurfaceColor() === $preset['surface'];
    }

    /**
     * @param array{
     *     accentDark: string,
     *     accentDeepDark: string,
     *     dangerDark: string,
     *     warnDark: string,
     *     paperDark: string,
     *     inkDark: string,
     *     surfaceDark: string
     * } $preset
     */
    private static function darkColorsMatch(SiteAppearance $appearance, array $preset): bool
    {
        return $appearance->getAccentColorDark() === $preset['accentDark']
            && $appearance->getAccentDeepColorDark() === $preset['accentDeepDark']
            && $appearance->getDangerColorDark() === $preset['dangerDark']
            && $appearance->getWarnColorDark() === $preset['warnDark']
            && $appearance->getPaperColorDark() === $preset['paperDark']
            && $appearance->getInkColorDark() === $preset['inkDark']
            && $appearance->getSurfaceColorDark() === $preset['surfaceDark'];
    }
}
