<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\NotificationOutboundFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NotificationOutboundFormatterTest extends TestCase
{
    public function testFormatsDiscordTeamsTelegramAndHttp(): void
    {
        $formatter = new NotificationOutboundFormatter();
        $payload = [
            'event' => 'issue.new',
            'summary' => 'Boom',
            'url' => 'https://beacon.test/i/1',
        ];

        $discord = $formatter->httpRequest(NotificationDestinationType::Discord, 'https://discord.com/api/webhooks/1/x', $payload);
        self::assertSame('Boom', $discord['json']['content']);
        self::assertSame('https://beacon.test/i/1', $discord['json']['embeds'][0]['url']);

        $teams = $formatter->httpRequest(NotificationDestinationType::Teams, 'https://outlook.office.com/webhook/x', $payload);
        self::assertSame('MessageCard', $teams['json']['@type']);
        self::assertSame('Boom', $teams['json']['text']);

        $telegram = $formatter->httpRequest(NotificationDestinationType::Telegram, '123:ABC@-10042', $payload);
        self::assertSame('https://api.telegram.org/bot123:ABC/sendMessage', $telegram['url']);
        self::assertSame('-10042', $telegram['json']['chat_id']);
        self::assertSame('Boom', $telegram['json']['text']);

        $http = $formatter->httpRequest(NotificationDestinationType::Http, 'https://example.com/hook', $payload);
        self::assertSame($payload, $http['json']);
    }

    public function testRejectsInvalidTelegramEndpoint(): void
    {
        $formatter = new NotificationOutboundFormatter();
        $this->expectException(InvalidArgumentException::class);
        $formatter->parseTelegramEndpoint('not-valid');
    }
}
