<?php

declare(strict_types=1);

namespace App\Issues;

/**
 * Cross-project assignment list scopes for the dashboard Assignments panel.
 */
enum AssignmentScope: string
{
    case Mine = 'mine';
    case Teammates = 'teammates';
    case Unassigned = 'unassigned';
    case All = 'all';

    public static function tryFromQuery(?string $value): self
    {
        if (!\is_string($value) || '' === $value) {
            return self::Mine;
        }

        return self::tryFrom($value) ?? self::Mine;
    }
}
