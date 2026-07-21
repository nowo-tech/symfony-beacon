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
            'project' => ['name' => 'Acme'],
            'issue' => [
                'title' => 'Boom',
                'level' => 'error',
                'culprit' => 'App\\Fail::run',
            ],
        ];

        $discord = $formatter->httpRequest(NotificationDestinationType::Discord, 'https://discord.com/api/webhooks/1/x', $payload);
        self::assertSame('Boom', $discord['json']['content']);
        self::assertSame('https://beacon.test/i/1', $discord['json']['embeds'][0]['url']);
        self::assertNotEmpty($discord['json']['embeds'][0]['fields']);

        $teams = $formatter->httpRequest(NotificationDestinationType::Teams, 'https://outlook.office.com/webhook/x', $payload);
        self::assertSame('MessageCard', $teams['json']['@type']);
        self::assertSame('Boom', $teams['json']['text']);
        self::assertNotEmpty($teams['json']['sections'][0]['facts']);

        $telegram = $formatter->httpRequest(NotificationDestinationType::Telegram, '123:ABC@-10042', $payload);
        self::assertSame('https://api.telegram.org/bot123:ABC/sendMessage', $telegram['url']);
        self::assertSame('-10042', $telegram['json']['chat_id']);
        self::assertStringContainsString('Boom', (string) $telegram['json']['text']);
        self::assertStringContainsString('Level: error', (string) $telegram['json']['text']);

        $http = $formatter->httpRequest(NotificationDestinationType::Http, 'https://example.com/hook', $payload);
        self::assertSame($payload, $http['json']);
    }

    public function testFormatsSlackSampleWithAttachment(): void
    {
        $formatter = new NotificationOutboundFormatter();
        $payload = [
            'event' => 'test',
            'summary' => '[TEST] Slack sample',
            'url' => 'https://beacon.test/settings',
            'project' => ['name' => 'Acme'],
            'issue' => [
                'title' => 'Sample exception',
                'level' => 'error',
                'culprit' => 'App\\Sample::index',
            ],
            'test' => true,
        ];

        $slack = $formatter->httpRequest(NotificationDestinationType::Slack, 'https://hooks.slack.com/services/T/B/X', $payload);
        self::assertSame('[TEST] Slack sample', $slack['json']['text']);
        self::assertSame($payload, $slack['json']['beacon']);
        self::assertSame('#C9A227', $slack['json']['attachments'][0]['color']);
        self::assertSame('Beacon · sample send', $slack['json']['attachments'][0]['footer']);
    }

    public function testEmailBodyIncludesFactsAndUrl(): void
    {
        $formatter = new NotificationOutboundFormatter();
        $body = $formatter->emailBody([
            'summary' => '[TEST] Email sample',
            'url' => 'https://beacon.test/settings',
            'project' => ['name' => 'Acme'],
            'issue' => ['title' => 'Sample', 'level' => 'error'],
            'test' => true,
        ]);

        self::assertStringContainsString('[TEST] Email sample', $body);
        self::assertStringContainsString('Project: Acme', $body);
        self::assertStringContainsString('https://beacon.test/settings', $body);
    }

    public function testRejectsInvalidTelegramEndpoint(): void
    {
        $formatter = new NotificationOutboundFormatter();
        $this->expectException(InvalidArgumentException::class);
        $formatter->parseTelegramEndpoint('not-valid');
    }
}
