<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Enum\NotificationDestinationType;
use InvalidArgumentException;

/**
 * Builds type-specific outbound request bodies / addresses for notification delivery.
 */
final class NotificationOutboundFormatter
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array{url: string, json: array<string, mixed>}
     */
    public function httpRequest(NotificationDestinationType $type, string $endpoint, array $payload): array
    {
        $summary = (string) ($payload['summary'] ?? 'Beacon notification');

        return match ($type) {
            NotificationDestinationType::Slack => [
                'url' => $endpoint,
                'json' => [
                    'text' => $summary,
                    'beacon' => $payload,
                ],
            ],
            NotificationDestinationType::Discord => [
                'url' => $endpoint,
                'json' => [
                    'content' => $summary,
                    'embeds' => [array_filter([
                        'title' => (string) ($payload['event'] ?? 'Beacon'),
                        'description' => $summary,
                        'url' => isset($payload['url']) && \is_string($payload['url']) && '' !== $payload['url']
                            ? $payload['url']
                            : null,
                        'color' => 0x1F6F54,
                    ], static fn (mixed $v): bool => null !== $v)],
                ],
            ],
            NotificationDestinationType::Teams => [
                'url' => $endpoint,
                'json' => [
                    '@type' => 'MessageCard',
                    '@context' => 'https://schema.org/extensions',
                    'summary' => $summary,
                    'themeColor' => '1F6F54',
                    'title' => (string) ($payload['event'] ?? 'Beacon notification'),
                    'text' => $summary,
                    'potentialAction' => isset($payload['url']) ? [[
                        '@type' => 'OpenUri',
                        'name' => 'Open in Beacon',
                        'targets' => [[
                            'os' => 'default',
                            'uri' => (string) $payload['url'],
                        ]],
                    ]] : [],
                ],
            ],
            NotificationDestinationType::Telegram => $this->telegramRequest($endpoint, $summary),
            NotificationDestinationType::Http => [
                'url' => $endpoint,
                'json' => $payload,
            ],
            NotificationDestinationType::Email => throw new InvalidArgumentException('Email destinations are not delivered over HTTP.'),
        };
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

        $token = substr($endpoint, 0, $at);
        $chatId = substr($endpoint, $at + 1);
        if ('' === $token || '' === $chatId) {
            throw new InvalidArgumentException('Telegram endpoint must be bot_token@chat_id.');
        }

        return ['token' => $token, 'chat_id' => $chatId];
    }

    /**
     * @return array{url: string, json: array<string, mixed>}
     */
    private function telegramRequest(string $endpoint, string $summary): array
    {
        $parts = $this->parseTelegramEndpoint($endpoint);

        return [
            'url' => \sprintf('https://api.telegram.org/bot%s/sendMessage', $parts['token']),
            'json' => [
                'chat_id' => $parts['chat_id'],
                'text' => $summary,
                'disable_web_page_preview' => true,
            ],
        ];
    }
}
