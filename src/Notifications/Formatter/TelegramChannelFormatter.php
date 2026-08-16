<?php

declare(strict_types=1);

namespace App\Notifications\Formatter;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use InvalidArgumentException;

final class TelegramChannelFormatter implements ChannelHttpFormatterInterface
{
    public function supports(NotificationDestinationType $type): bool
    {
        return NotificationDestinationType::Telegram === $type;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{url: string, json: array<string, mixed>}
     */
    public function format(string $endpoint, array $payload, ?NotificationDestination $destination): array
    {
        $summary = (string) ($payload['summary'] ?? 'Beacon notification');
        $parts = $this->parseTelegramEndpoint($endpoint);

        return [
            'url' => \sprintf('https://api.telegram.org/bot%s/sendMessage', $parts['token']),
            'json' => [
                'chat_id' => $parts['chat_id'],
                'text' => OutboundPayloadFacts::plainTextBody($payload, $summary),
                'disable_web_page_preview' => true,
            ],
        ];
    }

    /**
     * Telegram endpoint format: `BOT_TOKEN@CHAT_ID` (chat id may be negative for groups).
     *
     * @return array{token: string, chat_id: string}
     */
    public function parseTelegramEndpoint(string $endpoint): array
    {
        $endpoint = trim($endpoint);
        $at = strrpos($endpoint, '@');
        if (false === $at || 0 === $at || $at === \strlen($endpoint) - 1) {
            throw new InvalidArgumentException('Telegram endpoint must be bot_token@chat_id.');
        }

        return [
            'token' => substr($endpoint, 0, $at),
            'chat_id' => substr($endpoint, $at + 1),
        ];
    }
}
