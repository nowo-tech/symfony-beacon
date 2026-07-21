<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Parses Beacon event timestamps, preserving fractional seconds when present.
 */
final class EventTimestampParser
{
    public function parse(mixed $value): ?DateTimeImmutable
    {
        if (\is_string($value) && '' !== $value) {
            try {
                return new DateTimeImmutable($value);
            } catch (Exception) {
                // Fall through to numeric handling when possible.
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $float = (float) $value;
        $formatted = \sprintf('%.6F', $float);
        $parsed = DateTimeImmutable::createFromFormat('U.u', $formatted);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->setTimezone(new DateTimeZone('UTC'));
        }

        return new DateTimeImmutable('@'.(int) $float)->setTimezone(new DateTimeZone('UTC'));
    }
}
