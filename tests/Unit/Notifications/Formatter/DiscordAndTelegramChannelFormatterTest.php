<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Formatter;

use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Formatter\DiscordChannelFormatter;
use App\Notifications\Formatter\TelegramChannelFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DiscordAndTelegramChannelFormatterTest extends TestCase
{
    public function testDiscordSupportsAndFormatsSamplePayload(): void
    {
        $formatter = new DiscordChannelFormatter();
        self::assertTrue($formatter->supports(NotificationDestinationType::Discord));
        self::assertFalse($formatter->supports(NotificationDestinationType::Slack));

        $formatted = $formatter->format('https://discord.com/api/webhooks/1/x', [
            'event' => 'issue.new',
            'summary' => 'Boom',
            'url' => 'https://beacon.test/i/1',
            'project' => ['name' => 'Acme'],
            'issue' => ['title' => 'Boom', 'level' => 'error'],
            'test' => true,
        ], null);

        self::assertSame('Boom', $formatted['json']['content']);
        self::assertSame(0xC9A227, $formatted['json']['embeds'][0]['color']);
        self::assertSame('Beacon · sample send', $formatted['json']['embeds'][0]['footer']['text']);
        self::assertSame('https://beacon.test/i/1', $formatted['json']['embeds'][0]['url']);

        $withoutUrl = $formatter->format('https://discord.com/api/webhooks/1/x', [
            'event' => 'issue.new',
            'summary' => 'Boom',
            'url' => '',
        ], null);
        self::assertArrayNotHasKey('url', $withoutUrl['json']['embeds'][0]);
    }

    public function testTelegramParsesEndpointAndRejectsInvalid(): void
    {
        $formatter = new TelegramChannelFormatter();
        self::assertTrue($formatter->supports(NotificationDestinationType::Telegram));

        $parsed = $formatter->parseTelegramEndpoint('123:ABC@-10042');
        self::assertSame('123:ABC', $parsed['token']);
        self::assertSame('-10042', $parsed['chat_id']);

        $formatted = $formatter->format('123:ABC@-10042', [
            'summary' => 'Hi',
            'project' => ['name' => 'Acme'],
        ], null);
        self::assertSame('https://api.telegram.org/bot123:ABC/sendMessage', $formatted['url']);
        self::assertSame('-10042', $formatted['json']['chat_id']);
        self::assertStringContainsString('Hi', (string) $formatted['json']['text']);

        $this->expectException(InvalidArgumentException::class);
        $formatter->parseTelegramEndpoint('missing-at-sign');
    }
}
