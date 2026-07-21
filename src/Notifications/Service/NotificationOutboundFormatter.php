<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Enum\NotificationDestinationType;
use InvalidArgumentException;

/**
 * Builds type-specific outbound request bodies / addresses for notification delivery.
 *
 * Each third-party channel gets a native wire format (Slack attachments, Discord embeds,
 * Teams MessageCard, Telegram text, raw JSON for HTTP).
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
                    'attachments' => [$this->slackAttachment($payload, $summary)],
                    'beacon' => $payload,
                ],
            ],
            NotificationDestinationType::Discord => [
                'url' => $endpoint,
                'json' => [
                    'content' => $summary,
                    'embeds' => [$this->discordEmbed($payload, $summary)],
                ],
            ],
            NotificationDestinationType::Teams => [
                'url' => $endpoint,
                'json' => $this->teamsMessageCard($payload, $summary),
            ],
            NotificationDestinationType::Telegram => $this->telegramRequest($endpoint, $this->plainTextBody($payload, $summary)),
            NotificationDestinationType::Http => [
                'url' => $endpoint,
                'json' => $payload,
            ],
            NotificationDestinationType::Email => throw new InvalidArgumentException('Email destinations are not delivered over HTTP.'),
        };
    }

    /**
     * Human-readable body for email (and shared with Telegram).
     *
     * @param array<string, mixed> $payload
     */
    public function emailBody(array $payload): string
    {
        $summary = (string) ($payload['summary'] ?? 'Beacon notification');

        return $this->plainTextBody($payload, $summary);
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
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function slackAttachment(array $payload, string $summary): array
    {
        $attachment = [
            'color' => true === ($payload['test'] ?? false) ? '#C9A227' : '#1F6F54',
            'fallback' => $summary,
            'title' => (string) ($payload['event'] ?? 'Beacon notification'),
            'text' => $summary,
            'fields' => $this->factFields($payload),
            'footer' => true === ($payload['test'] ?? false) ? 'Beacon · sample send' : 'Beacon',
        ];

        if (isset($payload['url']) && \is_string($payload['url']) && '' !== $payload['url']) {
            $attachment['title_link'] = $payload['url'];
        }

        return $attachment;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function discordEmbed(array $payload, string $summary): array
    {
        $embed = array_filter([
            'title' => (string) ($payload['event'] ?? 'Beacon'),
            'description' => $summary,
            'url' => isset($payload['url']) && \is_string($payload['url']) && '' !== $payload['url']
                ? $payload['url']
                : null,
            'color' => true === ($payload['test'] ?? false) ? 0xC9A227 : 0x1F6F54,
            'fields' => $this->discordFields($payload),
            'footer' => [
                'text' => true === ($payload['test'] ?? false) ? 'Beacon · sample send' : 'Beacon',
            ],
        ], static fn (mixed $v): bool => null !== $v && [] !== $v);

        return $embed;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function teamsMessageCard(array $payload, string $summary): array
    {
        $facts = [];
        foreach ($this->factFields($payload) as $field) {
            $facts[] = [
                'name' => (string) ($field['title'] ?? ''),
                'value' => (string) ($field['value'] ?? ''),
            ];
        }

        $card = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => $summary,
            'themeColor' => true === ($payload['test'] ?? false) ? 'C9A227' : '1F6F54',
            'title' => (string) ($payload['event'] ?? 'Beacon notification'),
            'text' => $summary,
            'sections' => [] !== $facts ? [['facts' => $facts]] : [],
            'potentialAction' => [],
        ];

        if (isset($payload['url']) && \is_string($payload['url']) && '' !== $payload['url']) {
            $card['potentialAction'] = [[
                '@type' => 'OpenUri',
                'name' => 'Open in Beacon',
                'targets' => [[
                    'os' => 'default',
                    'uri' => $payload['url'],
                ]],
            ]];
        }

        return $card;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{title: string, value: string, short: bool}>
     */
    private function factFields(array $payload): array
    {
        $fields = [];
        $project = $payload['project'] ?? null;
        if (\is_array($project) && isset($project['name']) && \is_scalar($project['name'])) {
            $fields[] = ['title' => 'Project', 'value' => (string) $project['name'], 'short' => true];
        }

        $issue = $payload['issue'] ?? null;
        if (\is_array($issue)) {
            if (isset($issue['level']) && \is_scalar($issue['level'])) {
                $fields[] = ['title' => 'Level', 'value' => (string) $issue['level'], 'short' => true];
            }
            if (isset($issue['title']) && \is_scalar($issue['title'])) {
                $fields[] = ['title' => 'Issue', 'value' => (string) $issue['title'], 'short' => false];
            }
            if (isset($issue['culprit']) && \is_scalar($issue['culprit']) && '' !== (string) $issue['culprit']) {
                $fields[] = ['title' => 'Culprit', 'value' => (string) $issue['culprit'], 'short' => false];
            }
        }

        if (true === ($payload['test'] ?? false)) {
            $fields[] = ['title' => 'Sample', 'value' => 'yes', 'short' => true];
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{name: string, value: string, inline: bool}>
     */
    private function discordFields(array $payload): array
    {
        $fields = [];
        foreach ($this->factFields($payload) as $field) {
            $fields[] = [
                'name' => $field['title'],
                'value' => $field['value'],
                'inline' => $field['short'],
            ];
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function plainTextBody(array $payload, string $summary): string
    {
        $lines = [$summary];
        foreach ($this->factFields($payload) as $field) {
            if ('Sample' === $field['title']) {
                continue;
            }
            $lines[] = $field['title'].': '.$field['value'];
        }
        if (isset($payload['url']) && \is_string($payload['url']) && '' !== $payload['url']) {
            $lines[] = '';
            $lines[] = $payload['url'];
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{url: string, json: array<string, mixed>}
     */
    private function telegramRequest(string $endpoint, string $text): array
    {
        $parts = $this->parseTelegramEndpoint($endpoint);

        return [
            'url' => \sprintf('https://api.telegram.org/bot%s/sendMessage', $parts['token']),
            'json' => [
                'chat_id' => $parts['chat_id'],
                'text' => $text,
                'disable_web_page_preview' => true,
            ],
        ];
    }
}
