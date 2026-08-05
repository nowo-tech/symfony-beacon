<?php

declare(strict_types=1);

namespace App\Shared\Appearance;

use App\Shared\Appearance\Entity\SiteAppearance;

/**
 * Resolves card vs control border-radius tokens for a site corner style.
 *
 * Cards use a larger radius; inputs and buttons stay subtler (“en menor medida”).
 */
final class AppearanceCornerStyles
{
    /**
     * @return array{card: string, control: string}
     */
    public static function radii(string $style): array
    {
        return match ($style) {
            SiteAppearance::CORNER_SHARP => [
                'card' => '0.125rem',
                'control' => '0.0625rem',
            ],
            SiteAppearance::CORNER_ROUNDED => [
                'card' => '1.25rem',
                'control' => '0.5rem',
            ],
            default => [
                'card' => '0.75rem',
                'control' => '0.375rem',
            ],
        };
    }

    public static function isValid(string $style): bool
    {
        return \in_array($style, SiteAppearance::CORNER_STYLES, true);
    }
}
