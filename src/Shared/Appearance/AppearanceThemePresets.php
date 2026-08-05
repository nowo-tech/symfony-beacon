<?php

declare(strict_types=1);

namespace App\Shared\Appearance;

use App\Shared\Appearance\Entity\SiteAppearance;

/**
 * Named appearance palettes that replace all site color fields when applied.
 *
 * Each preset defines a full light + dark companion so the user day/night toggle
 * still works. The mode tag (light|dark) is the primary design intent for UI grouping.
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

    public static function apply(SiteAppearance $appearance, string $id): bool
    {
        $preset = self::get($id);
        if (null === $preset) {
            return false;
        }

        $appearance
            ->setAccentColor($preset['accent'])
            ->setAccentDeepColor($preset['accentDeep'])
            ->setAccentColorDark($preset['accentDark'])
            ->setAccentDeepColorDark($preset['accentDeepDark'])
            ->setDangerColor($preset['danger'])
            ->setDangerColorDark($preset['dangerDark'])
            ->setWarnColor($preset['warn'])
            ->setWarnColorDark($preset['warnDark'])
            ->setPaperColor($preset['paper'])
            ->setPaperColorDark($preset['paperDark'])
            ->setInkColor($preset['ink'])
            ->setInkColorDark($preset['inkDark'])
            ->setSurfaceColor($preset['surface'])
            ->setSurfaceColorDark($preset['surfaceDark'])
            ->setThemeId($preset['id']);

        return true;
    }

    /**
     * Resolve which named preset matches the current color fields, if any.
     */
    public static function matchId(SiteAppearance $appearance): string
    {
        foreach (self::all() as $preset) {
            if (self::colorsMatch($appearance, $preset)) {
                return $preset['id'];
            }
        }

        return self::CUSTOM;
    }

    /**
     * @param array{
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
     * } $preset
     */
    private static function colorsMatch(SiteAppearance $appearance, array $preset): bool
    {
        return $appearance->getAccentColor() === $preset['accent']
            && $appearance->getAccentDeepColor() === $preset['accentDeep']
            && $appearance->getAccentColorDark() === $preset['accentDark']
            && $appearance->getAccentDeepColorDark() === $preset['accentDeepDark']
            && $appearance->getDangerColor() === $preset['danger']
            && $appearance->getDangerColorDark() === $preset['dangerDark']
            && $appearance->getWarnColor() === $preset['warn']
            && $appearance->getWarnColorDark() === $preset['warnDark']
            && $appearance->getPaperColor() === $preset['paper']
            && $appearance->getPaperColorDark() === $preset['paperDark']
            && $appearance->getInkColor() === $preset['ink']
            && $appearance->getInkColorDark() === $preset['inkDark']
            && $appearance->getSurfaceColor() === $preset['surface']
            && $appearance->getSurfaceColorDark() === $preset['surfaceDark'];
    }
}
