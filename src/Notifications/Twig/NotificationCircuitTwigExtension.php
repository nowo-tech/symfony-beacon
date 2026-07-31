<?php

declare(strict_types=1);

namespace App\Notifications\Twig;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Service\NotificationCircuitBreaker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers for notification circuit-breaker UI (`039`).
 */
final class NotificationCircuitTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationCircuitBreaker $circuitBreaker,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('beacon_notification_circuit_open', $this->isCircuitOpen(...)),
        ];
    }

    public function isCircuitOpen(NotificationDestination $destination): bool
    {
        return $this->circuitBreaker->isOpen($destination);
    }
}
