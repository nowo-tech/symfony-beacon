<?php

declare(strict_types=1);

namespace App\Notifications\Formatter;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;

final class DiscordChannelFormatter implements ChannelHttpFormatterInterface
{
    public function supports(NotificationDestinationType $type): bool
    {
        return NotificationDestinationType::Discord === $type;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{url: string, json: array<string, mixed>}
     */
    public function format(string $endpoint, array $payload, ?NotificationDestination $destination): array
    {
        $summary = (string) ($payload['summary'] ?? 'Beacon notification');

        return [
            'url' => $endpoint,
            'json' => [
                'content' => $summary,
                'embeds' => [$this->discordEmbed($payload, $summary)],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function discordEmbed(array $payload, string $summary): array
    {
        return array_filter([
            'title' => (string) ($payload['event'] ?? 'Beacon'),
            'description' => $summary,
            'url' => isset($payload['url']) && \is_string($payload['url']) && '' !== $payload['url'] ? $payload['url'] : null,
            'color' => true === ($payload['test'] ?? false) ? 0xC9A227 : 0x1F6F54,
            'fields' => OutboundPayloadFacts::discordFields($payload),
            'footer' => [
                'text' => true === ($payload['test'] ?? false) ? 'Beacon · sample send' : 'Beacon',
            ],
        ], static fn (mixed $value): bool => null !== $value && [] !== $value);
    }
}
