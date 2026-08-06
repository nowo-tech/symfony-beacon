<?php

declare(strict_types=1);

namespace App\Notifications\Formatter;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;

final class HttpChannelFormatter implements ChannelHttpFormatterInterface
{
    public function supports(NotificationDestinationType $type): bool
    {
        return NotificationDestinationType::Http === $type;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{url: string, json: array<string, mixed>}
     */
    public function format(string $endpoint, array $payload, ?NotificationDestination $destination): array
    {
        return [
            'url' => $endpoint,
            'json' => $payload,
        ];
    }
}
