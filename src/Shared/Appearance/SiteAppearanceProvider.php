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

    /**
     * Inline CSS that overrides Beacon accent tokens for light and dark themes.
     */
    public function getCssOverrides(): string
    {
        $a = $this->get();

        return implode("\n", [
            ':root, [data-theme="light"] {',
            \sprintf('  --beacon-moss: %s;', $a->getAccentColor()),
            \sprintf('  --beacon-moss-deep: %s;', $a->getAccentDeepColor()),
            \sprintf('  --beacon-alert: %s;', $a->getDangerColor()),
            '}',
            '[data-theme="dark"] {',
            \sprintf('  --beacon-moss: %s;', $a->getAccentColorDark()),
            \sprintf('  --beacon-moss-deep: %s;', $a->getAccentDeepColorDark()),
            \sprintf('  --beacon-alert: %s;', $a->getDangerColorDark()),
            '}',
        ]);
    }
}
