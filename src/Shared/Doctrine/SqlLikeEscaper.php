<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

/**
 * Escapes user input for SQL LIKE patterns (backslash, %, _).
 */
final class SqlLikeEscaper
{
    public static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
