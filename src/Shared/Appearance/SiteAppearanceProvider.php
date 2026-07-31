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

        return implode("\n", [
            ':root, [data-theme="light"] {',
            \sprintf('  --beacon-moss: %s;', $a->getAccentColor()),
            \sprintf('  --beacon-moss-deep: %s;', $a->getAccentDeepColor()),
            \sprintf('  --beacon-alert: %s;', $a->getDangerColor()),
            \sprintf('  --beacon-warn: %s;', $a->getWarnColor()),
            \sprintf('  --beacon-paper: %s;', $a->getPaperColor()),
            \sprintf('  --beacon-ink: %s;', $a->getInkColor()),
            \sprintf('  --beacon-surface: %s;', $a->getSurfaceColor()),
            '}',
            '[data-theme="dark"] {',
            \sprintf('  --beacon-moss: %s;', $a->getAccentColorDark()),
            \sprintf('  --beacon-moss-deep: %s;', $a->getAccentDeepColorDark()),
            \sprintf('  --beacon-alert: %s;', $a->getDangerColorDark()),
            \sprintf('  --beacon-warn: %s;', $a->getWarnColorDark()),
            \sprintf('  --beacon-paper: %s;', $a->getPaperColorDark()),
            \sprintf('  --beacon-ink: %s;', $a->getInkColorDark()),
            \sprintf('  --beacon-surface: %s;', $a->getSurfaceColorDark()),
            '}',
        ]);
    }
}
