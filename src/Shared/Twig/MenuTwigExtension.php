<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Menu\AdministrationMenuCurrentMarker;
use App\Shared\Menu\PreferencesMenuCurrentMarker;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers for Beacon sidebar current-state polish.
 */
final class MenuTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly PreferencesMenuCurrentMarker $preferencesMenuCurrentMarker,
        private readonly AdministrationMenuCurrentMarker $administrationMenuCurrentMarker,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('beacon_preferences_menu_current', $this->markPreferencesCurrent(...)),
            new TwigFunction('beacon_administration_menu_current', $this->markAdministrationCurrent(...)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $tree
     *
     * @return list<array<string, mixed>>
     */
    public function markPreferencesCurrent(array $tree, ?string $route): array
    {
        return $this->preferencesMenuCurrentMarker->mark($tree, $route);
    }

    /**
     * @param list<array<string, mixed>> $tree
     *
     * @return list<array<string, mixed>>
     */
    public function markAdministrationCurrent(array $tree, ?string $route): array
    {
        return $this->administrationMenuCurrentMarker->mark($tree, $route);
    }
}
