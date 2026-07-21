<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Evaluates whether a destination is inside its configured quiet-hours window.
 */
final class QuietHoursEvaluator
{
    public function isQuietHoursActive(NotificationDestination $destination, ?DateTimeImmutable $now = null): bool
    {
        if (!$destination->isQuietHoursEnabled()) {
            return false;
        }

        $start = $destination->getQuietHoursStart();
        $end = $destination->getQuietHoursEnd();
        if (null === $start || null === $end || '' === $start || '' === $end) {
            return false;
        }

        if ($start === $end) {
            return false;
        }

        try {
            $tz = new DateTimeZone($destination->getQuietHoursTimezone());
        } catch (Exception) {
            $tz = new DateTimeZone('UTC');
        }

        $now = ($now ?? new DateTimeImmutable('now'))->setTimezone($tz);
        $current = $now->format('H:i');

        if ($start < $end) {
            return $current >= $start && $current < $end;
        }

        // Overnight window (e.g. 22:00–07:00).
        return $current >= $start || $current < $end;
    }
}
