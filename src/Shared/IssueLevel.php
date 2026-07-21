<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Severity level for a grouped issue (Envelope / Sentry-compatible vocabulary).
 */
enum IssueLevel: string
{
    case Fatal = 'fatal';
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
    case Debug = 'debug';

    /**
     * Map an ingest/UI string to a known level; unknown values become Error.
     */
    public static function normalize(string $raw): self
    {
        $value = strtolower(trim($raw));

        return self::tryFrom($value) ?? self::Error;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
