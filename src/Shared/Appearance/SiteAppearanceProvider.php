<?php

declare(strict_types=1);

namespace App\Shared\Appearance;

use App\Shared\Appearance\Entity\SiteAppearance;
use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Resolves site look & feel for Twig (brand + CSS custom properties).
 */
final class SiteAppearanceProvider implements ResetInterface
{
    private ?SiteAppearance $cached = null;

    public function __construct(
        private readonly SiteAppearanceRepository $repository,
    ) {
    }

    public function reset(): void
    {
        $this->cached = null;
    }

    public function get(): SiteAppearance
    {
        return $this->cached ??= $this->repository->getOrCreate();
    }

    public function refresh(): SiteAppearance
    {
        $this->cached = null;

        return $this->get();
    }

    public function getBrandName(): string
    {
        return $this->get()->getBrandName();
    }

    public function getBrandEyebrow(): string
    {
        return $this->get()->getBrandEyebrow();
    }

    public function getThemeId(): string
    {
        return AppearanceThemePresets::matchLightId($this->get());
    }

    public function getThemeIdDark(): string
    {
        return AppearanceThemePresets::matchDarkId($this->get());
    }

    public function isFooterFixed(): bool
    {
        return $this->get()->isFooterFixed();
    }

    public function getCornerStyle(): string
    {
        return $this->get()->getCornerStyle();
    }

    public function getBorderStrength(): string
    {
        return $this->get()->getBorderStrength();
    }

    public function getAccentColor(): string
    {
        return $this->get()->getAccentColor();
    }

    public function getAccentDeepColor(): string
    {
        return $this->get()->getAccentDeepColor();
    }

    public function getAccentColorDark(): string
    {
        return $this->get()->getAccentColorDark();
    }

    public function getAccentDeepColorDark(): string
    {
        return $this->get()->getAccentDeepColorDark();
    }

    public function getDangerColor(): string
    {
        return $this->get()->getDangerColor();
    }

    public function getDangerColorDark(): string
    {
        return $this->get()->getDangerColorDark();
    }

    public function getWarnColor(): string
    {
        return $this->get()->getWarnColor();
    }

    public function getWarnColorDark(): string
    {
        return $this->get()->getWarnColorDark();
    }

    public function getPaperColor(): string
    {
        return $this->get()->getPaperColor();
    }

    public function getPaperColorDark(): string
    {
        return $this->get()->getPaperColorDark();
    }

    public function getInkColor(): string
    {
        return $this->get()->getInkColor();
    }

    public function getInkColorDark(): string
    {
        return $this->get()->getInkColorDark();
    }

    public function getSurfaceColor(): string
    {
        return $this->get()->getSurfaceColor();
    }

    public function getSurfaceColorDark(): string
    {
        return $this->get()->getSurfaceColorDark();
    }

    /**
     * Inline CSS that overrides Beacon palette tokens for light and dark themes.
     */
    public function getCssOverrides(): string
    {
        $a = $this->get();
        $radii = AppearanceCornerStyles::radii($a->getCornerStyle());
        $borders = AppearanceBorderStyles::tokens($a->getBorderStrength());

        return implode("\n", [
            ':root {',
            \sprintf('  --beacon-radius-card: %s;', $radii['card']),
            \sprintf('  --beacon-radius-control: %s;', $radii['control']),
            \sprintf('  --beacon-border-width: %s;', $borders['width']),
            '}',
            ':root, [data-theme="light"] {',
            ...$this->tokenBlock(
                moss: $a->getAccentColor(),
                mossDeep: $a->getAccentDeepColor(),
                alert: $a->getDangerColor(),
                warn: $a->getWarnColor(),
                paper: $a->getPaperColor(),
                ink: $a->getInkColor(),
                surface: $a->getSurfaceColor(),
                sandMix: $borders['sandMixLight'],
                dark: false,
            ),
            '}',
            '[data-theme="dark"] {',
            ...$this->tokenBlock(
                moss: $a->getAccentColorDark(),
                mossDeep: $a->getAccentDeepColorDark(),
                alert: $a->getDangerColorDark(),
                warn: $a->getWarnColorDark(),
                paper: $a->getPaperColorDark(),
                ink: $a->getInkColorDark(),
                surface: $a->getSurfaceColorDark(),
                sandMix: $borders['sandMixDark'],
                dark: true,
            ),
            '}',
        ]);
    }

    /**
     * @return list<string>
     */
    private function tokenBlock(
        string $moss,
        string $mossDeep,
        string $alert,
        string $warn,
        string $paper,
        string $ink,
        string $surface,
        int $sandMix,
        bool $dark,
    ): array {
        $lines = [
            \sprintf('  --beacon-moss: %s;', $moss),
            \sprintf('  --beacon-moss-deep: %s;', $mossDeep),
            \sprintf('  --beacon-alert: %s;', $alert),
            \sprintf('  --beacon-warn: %s;', $warn),
            \sprintf('  --beacon-paper: %s;', $paper),
            \sprintf('  --beacon-ink: %s;', $ink),
            \sprintf('  --beacon-surface: %s;', $surface),
            // Derived companions so named themes keep borders / shell / muted panels coherent.
            \sprintf('  --beacon-sand: color-mix(in srgb, %s %d%%, %s);', $ink, $sandMix, $paper),
            \sprintf('  --beacon-mist: color-mix(in srgb, %s 10%%, %s);', $moss, $paper),
            \sprintf('  --beacon-surface-muted: color-mix(in srgb, %s 5%%, %s);', $ink, $surface),
            \sprintf('  --beacon-shell-a: color-mix(in srgb, %s 8%%, %s);', $moss, $paper),
            \sprintf('  --beacon-shell-b: color-mix(in srgb, %s 6%%, %s);', $ink, $paper),
            \sprintf('  --beacon-mark: %s;', $moss),
            '  --color-sand: var(--beacon-sand);',
            '  --color-mist: var(--beacon-mist);',
            '  --color-ink: var(--beacon-ink);',
            '  --color-paper: var(--beacon-paper);',
            '  --color-moss: var(--beacon-moss);',
            '  --color-moss-deep: var(--beacon-moss-deep);',
            '  --color-alert: var(--beacon-alert);',
            '  --color-warn: var(--beacon-warn);',
            '  --color-surface: var(--beacon-surface);',
            '  --color-surface-muted: var(--beacon-surface-muted);',
        ];

        if ($dark) {
            $lines[] = '  --beacon-shadow: 0 0 0;';
        } else {
            $lines[] = \sprintf('  --beacon-shadow: %s;', $this->rgbChannels($ink));
        }

        return $lines;
    }

    private function rgbChannels(string $hex): string
    {
        $value = ltrim($hex, '#');
        if (6 !== \strlen($value) || !ctype_xdigit($value)) {
            return '15 28 24';
        }

        return \sprintf(
            '%d %d %d',
            hexdec(substr($value, 0, 2)),
            hexdec(substr($value, 2, 2)),
            hexdec(substr($value, 4, 2)),
        );
    }
}
