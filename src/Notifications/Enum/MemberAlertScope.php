<?php

declare(strict_types=1);

namespace App\Notifications\Enum;

/**
 * Whether member alerts apply to all project issues or only when the member is involved.
 */
enum MemberAlertScope: string
{
    case All = 'all';
    case Involved = 'involved';

    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        if (\is_string($value) && 'involved' === strtolower(trim($value))) {
            return self::Involved;
        }

        return self::All;
    }
}
