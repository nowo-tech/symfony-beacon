<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Formatter\ChannelHttpFormatterInterface;
use App\Notifications\Formatter\DiscordChannelFormatter;
use App\Notifications\Formatter\HttpChannelFormatter;
use App\Notifications\Formatter\OutboundPayloadFacts;
use App\Notifications\Formatter\SlackChannelFormatter;
use App\Notifications\Formatter\TeamsChannelFormatter;
use App\Notifications\Formatter\TelegramChannelFormatter;
use InvalidArgumentException;

/**
 * Builds type-specific outbound request bodies / addresses for notification delivery.
 *
 * Each third-party channel gets a native wire format (Slack attachments, Discord embeds,
 * Teams MessageCard, Telegram text, raw JSON for HTTP).
 */
final readonly class NotificationOutboundFormatter
{
    /**
     * @var list<ChannelHttpFormatterInterface>
     */
    private array $formatters;

    public function __construct(
        SlackChannelFormatter $slackFormatter,
        DiscordChannelFormatter $discordFormatter,
        TeamsChannelFormatter $teamsFormatter,
        private TelegramChannelFormatter $telegramFormatter,
        HttpChannelFormatter $httpFormatter,
    ) {
        $this->formatters = [
            $slackFormatter,
            $discordFormatter,
            $teamsFormatter,
            $this->telegramFormatter,
            $httpFormatter,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{url: string, json: array<string, mixed>}
     */
    public function httpRequest(
        NotificationDestinationType $type,
        string $endpoint,
        array $payload,
        ?NotificationDestination $destination = null,
    ): array {
        if (NotificationDestinationType::Email === $type) {
            throw new InvalidArgumentException('Email destinations are not delivered over HTTP.');
        }

        return $this->formatterFor($type)->format($endpoint, $payload, $destination);
    }

    /**
     * Human-readable body for email (and shared with Telegram).
     *
     * @param array<string, mixed> $payload
     */
    public function emailBody(array $payload): string
    {
        $summary = (string) ($payload['summary'] ?? 'Beacon notification');

        return OutboundPayloadFacts::plainTextBody($payload, $summary);
    }

    /**
     * Telegram endpoint format: `BOT_TOKEN@CHAT_ID` (chat id may be negative for groups).
     *
     * @return array{token: string, chat_id: string}
     */
    public function parseTelegramEndpoint(string $endpoint): array
    {
        return $this->telegramFormatter->parseTelegramEndpoint($endpoint);
    }

    /**
     * Resolves the HTTP formatter responsible for the given destination type.
     */
    private function formatterFor(NotificationDestinationType $type): ChannelHttpFormatterInterface
    {
        foreach ($this->formatters as $formatter) {
            if ($formatter->supports($type)) {
                return $formatter;
            }
        }

        throw new InvalidArgumentException('Unsupported notification destination type: '.$type->value);
    }
}
