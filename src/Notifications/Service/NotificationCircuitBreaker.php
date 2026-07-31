<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use DateTimeImmutable;

/**
 * Consecutive-failure circuit breaker for outbound notification destinations.
 */
final readonly class NotificationCircuitBreaker
{
    public function __construct(
        private InstanceOpsDefaults $opsDefaults,
    ) {
    }

    public function getThreshold(): int
    {
        return $this->opsDefaults->circuitBreakerThreshold();
    }

    public function getCooldownMinutes(): int
    {
        return $this->opsDefaults->circuitBreakerCooldownMinutes();
    }

    /**
     * Expire a cooled-down circuit so the next delivery may proceed (documented backoff).
     */
    public function maybeExpireCircuit(NotificationDestination $destination, ?DateTimeImmutable $now = null): void
    {
        $openedAt = $destination->getCircuitOpenedAt();
        if (!$openedAt instanceof DateTimeImmutable || $this->getCooldownMinutes() < 1) {
            return;
        }

        $now ??= new DateTimeImmutable();
        $expiresAt = $openedAt->modify(\sprintf('+%d minutes', $this->getCooldownMinutes()));
        if ($now >= $expiresAt) {
            $destination->clearCircuitOpenedAt();
        }
    }

    public function isOpen(NotificationDestination $destination, ?DateTimeImmutable $now = null): bool
    {
        $this->maybeExpireCircuit($destination, $now);

        return $destination->getCircuitOpenedAt() instanceof DateTimeImmutable;
    }

    public function shouldSkipDelivery(NotificationDestination $destination, bool $isSample = false): bool
    {
        if ($isSample) {
            return false;
        }

        return $this->isOpen($destination);
    }

    public function onSuccess(NotificationDestination $destination): void
    {
        $destination->resumeCircuit();
    }

    public function onFailure(NotificationDestination $destination, ?DateTimeImmutable $at = null): void
    {
        $destination->incrementConsecutiveFailures();
        if ($destination->getConsecutiveFailures() >= $this->getThreshold()) {
            $destination->openCircuit($at);
        }
    }
}
