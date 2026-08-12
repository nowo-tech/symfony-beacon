<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NotificationCircuitBreakerTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testOnFailureOpensAtThresholdAndOnSuccessResumes(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setNotificationCircuitBreakerThreshold(2);
            $settings->setNotificationCircuitBreakerCooldownMinutes(15);
        });

        $breaker = new NotificationCircuitBreaker($ops);
        $destination = new NotificationDestination();

        $breaker->onFailure($destination);
        self::assertFalse($breaker->isOpen($destination));
        self::assertSame(1, $destination->getConsecutiveFailures());

        $openedAt = new DateTimeImmutable();
        $breaker->onFailure($destination, $openedAt);
        self::assertTrue($breaker->isOpen($destination, $openedAt));
        self::assertSame($openedAt, $destination->getCircuitOpenedAt());
        self::assertTrue($breaker->shouldSkipDelivery($destination));
        self::assertFalse($breaker->shouldSkipDelivery($destination, true));

        $breaker->onSuccess($destination);
        self::assertFalse($breaker->isOpen($destination));
        self::assertSame(0, $destination->getConsecutiveFailures());
    }

    public function testMaybeExpireCircuitClearsAfterCooldown(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setNotificationCircuitBreakerThreshold(3);
            $settings->setNotificationCircuitBreakerCooldownMinutes(10);
        });

        $breaker = new NotificationCircuitBreaker($ops);
        $destination = new NotificationDestination();
        $openedAt = new DateTimeImmutable('2026-01-01T12:00:00+00:00');
        $destination->openCircuit($openedAt);

        $breaker->maybeExpireCircuit($destination, $openedAt->modify('+5 minutes'));
        self::assertNotNull($destination->getCircuitOpenedAt());

        $breaker->maybeExpireCircuit($destination, $openedAt->modify('+10 minutes'));
        self::assertNull($destination->getCircuitOpenedAt());
    }

    public function testCooldownBelowOneDoesNotExpire(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setNotificationCircuitBreakerThreshold(1);
            $settings->setNotificationCircuitBreakerCooldownMinutes(0);
        });

        $breaker = new NotificationCircuitBreaker($ops);
        $destination = new NotificationDestination();
        $destination->openCircuit(new DateTimeImmutable('2020-01-01T00:00:00+00:00'));

        $breaker->maybeExpireCircuit($destination, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        self::assertNotNull($destination->getCircuitOpenedAt());
        self::assertSame(1, $breaker->getThreshold());
        self::assertSame(0, $breaker->getCooldownMinutes());
    }
}
