<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Service\QuietHoursEvaluator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class QuietHoursEvaluatorTest extends TestCase
{
    public function testDisabledOrIncompleteReturnsFalse(): void
    {
        $evaluator = new QuietHoursEvaluator();
        $destination = new NotificationDestination();

        self::assertFalse($evaluator->isQuietHoursActive($destination));

        $destination->setQuietHoursEnabled(true);
        self::assertFalse($evaluator->isQuietHoursActive($destination));

        $destination->setQuietHoursStart('22:00');
        $destination->setQuietHoursEnd('22:00');
        self::assertFalse($evaluator->isQuietHoursActive($destination));
    }

    public function testSameDayWindow(): void
    {
        $evaluator = new QuietHoursEvaluator();
        $destination = new NotificationDestination();
        $destination->setQuietHoursEnabled(true);
        $destination->setQuietHoursTimezone('UTC');
        $destination->setQuietHoursStart('09:00');
        $destination->setQuietHoursEnd('17:00');

        $inside = new DateTimeImmutable('2026-08-06 12:00:00', new DateTimeZone('UTC'));
        $outside = new DateTimeImmutable('2026-08-06 18:00:00', new DateTimeZone('UTC'));

        self::assertTrue($evaluator->isQuietHoursActive($destination, $inside));
        self::assertFalse($evaluator->isQuietHoursActive($destination, $outside));
    }

    public function testOvernightWindowAndInvalidTimezoneFallsBackToUtc(): void
    {
        $evaluator = new QuietHoursEvaluator();
        $destination = new NotificationDestination();
        $destination->setQuietHoursEnabled(true);
        $destination->setQuietHoursTimezone('Not/AZone');
        $destination->setQuietHoursStart('22:00');
        $destination->setQuietHoursEnd('07:00');

        $late = new DateTimeImmutable('2026-08-06 23:30:00', new DateTimeZone('UTC'));
        $early = new DateTimeImmutable('2026-08-06 06:00:00', new DateTimeZone('UTC'));
        $day = new DateTimeImmutable('2026-08-06 12:00:00', new DateTimeZone('UTC'));

        self::assertTrue($evaluator->isQuietHoursActive($destination, $late));
        self::assertTrue($evaluator->isQuietHoursActive($destination, $early));
        self::assertFalse($evaluator->isQuietHoursActive($destination, $day));
    }
}
