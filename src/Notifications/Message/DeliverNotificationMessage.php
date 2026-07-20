<?php

declare(strict_types=1);

namespace App\Notifications\Message;

/**
 * Async delivery of one alert to one notification destination.
 *
 * @param array<string, mixed> $payload Stable JSON body for HTTP destinations (also used to build Slack text)
 */
final readonly class DeliverNotificationMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $destinationId,
        public array $payload,
    ) {
    }
}
