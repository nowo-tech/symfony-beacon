<?php

declare(strict_types=1);

namespace App\Shared\Appearance;

/**
 * Optional second-level tabs under Colors.
 */
enum AppearanceSettingsSubtab: string
{
    case Accents = 'accents';
    case Status = 'status';
    case Surfaces = 'surfaces';

    public function tabLabelKey(): string
    {
        return 'appearance.subtab.'.$this->value;
    }

    public static function routeRequirement(): string
    {
        return implode('|', array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        ));
    }
}
