<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use DateTimeImmutable;

/**
 * Appends bounded delivery history and keeps the summary fields in sync.
 */
final readonly class NotificationDeliveryHistoryRecorder
{
    public function __construct(
        private NotificationDeliveryAttemptRepository $attemptRepository,
        private int $historyLimit,
    ) {
    }

    public function recordSuccess(NotificationDestination $destination, ?DateTimeImmutable $deliveredAt = null): void
    {
        $timestamp = $deliveredAt ?? new DateTimeImmutable();

        $destination->recordDeliverySuccess($timestamp);
        $this->attemptRepository->record($destination, true, attemptedAt: $timestamp);
        $this->attemptRepository->removeAll($destination->trimDeliveryAttempts($this->historyLimit));
    }

    public function recordFailure(
        NotificationDestination $destination,
        string $error,
        ?DateTimeImmutable $deliveredAt = null,
    ): void {
        $timestamp = $deliveredAt ?? new DateTimeImmutable();

        $destination->recordDeliveryFailure($error, $timestamp);
        $this->attemptRepository->record($destination, false, $error, $timestamp);
        $this->attemptRepository->removeAll($destination->trimDeliveryAttempts($this->historyLimit));
    }
}
