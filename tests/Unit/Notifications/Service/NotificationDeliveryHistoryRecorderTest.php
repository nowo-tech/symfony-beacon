<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Notifications\Entity\NotificationDeliveryAttempt;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDeliveryHistoryRecorder;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class NotificationDeliveryHistoryRecorderTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    private NotificationDeliveryAttemptRepository&MockObject $attemptRepository;
    private NotificationDeliveryHistoryRecorder $recorder;

    protected function setUp(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setNotificationDeliveryHistoryLimit(25);
            $settings->setNotificationCircuitBreakerThreshold(2);
            $settings->setNotificationCircuitBreakerCooldownMinutes(15);
        });
        $this->attemptRepository = $this->createMock(NotificationDeliveryAttemptRepository::class);
        $this->recorder = new NotificationDeliveryHistoryRecorder(
            $this->attemptRepository,
            new NotificationCircuitBreaker($ops),
            $ops,
        );
    }

    public function testRecordSuccessPersistsAttemptAndTrims(): void
    {
        $destination = new NotificationDestination();
        $at = new DateTimeImmutable('2026-08-13T10:00:00+00:00');

        $this->attemptRepository->expects(self::once())
            ->method('record')
            ->with($destination, true, null, $at)
            ->willReturn(new NotificationDeliveryAttempt());
        $this->attemptRepository->expects(self::once())
            ->method('trimOlderThanKeep')
            ->with($destination, 25)
            ->willReturn(0);

        $this->recorder->recordSuccess($destination, $at);

        self::assertSame($at, $destination->getLastDeliveryAt());
        self::assertSame(0, $destination->getConsecutiveFailures());
    }

    public function testRecordFailureOpensCircuitAfterThreshold(): void
    {
        $destination = new NotificationDestination();
        $at = new DateTimeImmutable('2026-08-13T11:00:00+00:00');

        $this->attemptRepository->expects(self::exactly(2))
            ->method('record')
            ->with($destination, false, 'boom', self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn(new NotificationDeliveryAttempt());
        $this->attemptRepository->expects(self::exactly(2))
            ->method('trimOlderThanKeep')
            ->with($destination, 25)
            ->willReturn(0);

        $this->recorder->recordFailure($destination, 'boom', $at);
        self::assertSame(1, $destination->getConsecutiveFailures());
        self::assertNull($destination->getCircuitOpenedAt());

        $this->recorder->recordFailure($destination, 'boom', $at);
        self::assertSame(2, $destination->getConsecutiveFailures());
        self::assertSame($at, $destination->getCircuitOpenedAt());
        self::assertSame('boom', $destination->getLastDeliveryError());
    }
}
