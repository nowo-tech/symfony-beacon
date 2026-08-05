<?php

declare(strict_types=1);

namespace App\Shared\Appearance;

use App\Shared\Appearance\Entity\SiteAppearance;

/**
 * Resolves how strongly UI borders read against paper (light and dark).
 *
 * Higher ink mix into sand = more defined edges; width reinforces strong mode.
 */
final class AppearanceBorderStyles
{
    /**
     * @return array{sandMixLight: int, sandMixDark: int, width: string}
     */
    public static function tokens(string $strength): array
    {
        return match ($strength) {
            SiteAppearance::BORDER_SUBTLE => [
                'sandMixLight' => 7,
                'sandMixDark' => 9,
                'width' => '1px',
            ],
            SiteAppearance::BORDER_STRONG => [
                'sandMixLight' => 26,
                'sandMixDark' => 30,
                'width' => '1.5px',
            ],
            default => [
                'sandMixLight' => 14,
                'sandMixDark' => 16,
                'width' => '1px',
            ],
        };
    }

    public static function isValid(string $strength): bool
    {
        return \in_array($strength, SiteAppearance::BORDER_STRENGTHS, true);
    }
}
