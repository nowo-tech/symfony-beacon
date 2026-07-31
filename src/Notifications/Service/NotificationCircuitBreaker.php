<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Consecutive-failure circuit breaker for outbound notification destinations.
 */
final readonly class NotificationCircuitBreaker
{
    public function __construct(
        #[Autowire('%beacon.notifications.circuit_breaker_threshold%')]
        private int $threshold,
        #[Autowire('%beacon.notifications.circuit_breaker_cooldown_minutes%')]
        private int $cooldownMinutes,
    ) {
    }

    public function getThreshold(): int
    {
        return max(1, $this->threshold);
    }

    public function getCooldownMinutes(): int
    {
        return max(0, $this->cooldownMinutes);
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
