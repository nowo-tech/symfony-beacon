<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Formatter\DiscordChannelFormatter;
use App\Notifications\Formatter\HttpChannelFormatter;
use App\Notifications\Formatter\SlackChannelFormatter;
use App\Notifications\Formatter\TeamsChannelFormatter;
use App\Notifications\Formatter\TelegramChannelFormatter;
use App\Notifications\Service\InteractionActionToken;
use App\Notifications\Service\NotificationOutboundFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotificationOutboundFormatterTest extends TestCase
{
    private function formatter(): NotificationOutboundFormatter
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $actionToken = new InteractionActionToken();
        $urls->method('generate')->willReturnCallback(
            static function (string $name, array $parameters = []): string {
                if ('hooks_teams_actions' === $name) {
                    return 'https://beacon.test/hooks/teams/actions';
                }
                if ('hooks_teams_assign_me' === $name) {
                    return 'https://beacon.test/hooks/teams/assign-me?'.http_build_query($parameters);
                }

                return 'https://beacon.test/';
            },
        );

        return new NotificationOutboundFormatter(
            new SlackChannelFormatter(),
            new DiscordChannelFormatter(),
            new TeamsChannelFormatter($urls, $actionToken),
            new TelegramChannelFormatter(),
            new HttpChannelFormatter(),
        );
    }

    public function testFormatsDiscordTeamsTelegramAndHttp(): void
    {
        $formatter = $this->formatter();
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
        self::assertSame('OpenUri', $teams['json']['potentialAction'][0]['@type']);

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
        $formatter = $this->formatter();
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
        self::assertArrayNotHasKey('blocks', $slack['json']);
    }

    public function testAddsSlackResolveBlockWhenSigningSecretPresent(): void
    {
        $formatter = $this->formatter();
        $destination = new NotificationDestination();
        $destination->setType(NotificationDestinationType::Slack);
        $destination->setSigningSecret('secret');
        $destination->setEndpointUrl('https://hooks.slack.com/services/T/B/X');
        $destination->setLabel('Ops');
        $destination->setCategories(['error']);

        $payload = [
            'event' => 'issue.new',
            'summary' => 'Boom',
            'url' => 'https://beacon.test/i/1',
            'project' => [
                'uuid' => '11111111-1111-4111-8111-111111111111',
                'name' => 'Acme',
            ],
            'issue' => [
                'uuid' => '22222222-2222-4222-8222-222222222222',
                'title' => 'Boom',
                'level' => 'error',
            ],
        ];

        $slack = $formatter->httpRequest(
            NotificationDestinationType::Slack,
            'https://hooks.slack.com/services/T/B/X',
            $payload,
            $destination,
        );

        self::assertArrayHasKey('blocks', $slack['json']);
        $actions = $slack['json']['blocks'][1];
        self::assertSame('actions', $actions['type']);
        self::assertSame('beacon_resolve', $actions['elements'][0]['action_id']);
        self::assertSame('beacon_assign', $actions['elements'][1]['action_id']);
        $value = json_decode((string) $actions['elements'][0]['value'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('resolve', $value['a']);
        $assignValue = json_decode((string) $actions['elements'][1]['value'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('assign', $assignValue['a']);
        self::assertSame($destination->getUuid(), $value['d']);
        self::assertSame('11111111-1111-4111-8111-111111111111', $value['p']);
        self::assertSame('22222222-2222-4222-8222-222222222222', $value['i']);
    }

    public function testAddsTeamsResolveHttpPostWhenSigningSecretPresent(): void
    {
        $formatter = $this->formatter();
        $destination = new NotificationDestination();
        $destination->setType(NotificationDestinationType::Teams);
        $destination->setSigningSecret('teams-secret');
        $destination->setEndpointUrl('https://outlook.office.com/webhook/x');
        $destination->setLabel('Ops');
        $destination->setCategories(['error']);

        $payload = [
            'event' => 'issue.new',
            'summary' => 'Boom',
            'url' => 'https://beacon.test/i/1',
            'project' => [
                'uuid' => '11111111-1111-4111-8111-111111111111',
                'name' => 'Acme',
            ],
            'issue' => [
                'uuid' => '22222222-2222-4222-8222-222222222222',
                'title' => 'Boom',
                'level' => 'error',
            ],
        ];

        $teams = $formatter->httpRequest(
            NotificationDestinationType::Teams,
            'https://outlook.office.com/webhook/x',
            $payload,
            $destination,
        );

        $actions = $teams['json']['potentialAction'];
        self::assertCount(3, $actions);
        self::assertSame('OpenUri', $actions[0]['@type']);
        self::assertSame('Open in Beacon', $actions[0]['name']);
        self::assertSame('HttpPOST', $actions[1]['@type']);
        self::assertSame('Resolve', $actions[1]['name']);
        self::assertSame('https://beacon.test/hooks/teams/actions', $actions[1]['target']);
        $body = json_decode((string) $actions[1]['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('resolve', $body['a']);
        self::assertSame($destination->getUuid(), $body['d']);
        self::assertArrayHasKey('sig', $body);
        self::assertTrue(new InteractionActionToken()->isValidResolveToken('teams-secret', $body));

        self::assertSame('OpenUri', $actions[2]['@type']);
        self::assertSame('Assign to me', $actions[2]['name']);
        $assignUri = (string) ($actions[2]['targets'][0]['uri'] ?? '');
        self::assertStringContainsString('/hooks/teams/assign-me', $assignUri);
        self::assertStringContainsString('a=assign', $assignUri);
        parse_str((string) parse_url($assignUri, \PHP_URL_QUERY), $assignQuery);
        self::assertTrue(new InteractionActionToken()->isValidAssignToken('teams-secret', $assignQuery));
    }

    public function testTeamsAssignOpenUriOmittedWithoutSigningSecret(): void
    {
        $formatter = $this->formatter();
        $destination = new NotificationDestination();
        $destination->setType(NotificationDestinationType::Teams);
        $destination->setEndpointUrl('https://outlook.office.com/webhook/x');
        $destination->setLabel('Ops');
        $destination->setCategories(['error']);

        $payload = [
            'event' => 'issue.new',
            'summary' => 'Boom',
            'url' => 'https://beacon.test/i/1',
            'project' => [
                'uuid' => '11111111-1111-4111-8111-111111111111',
                'name' => 'Acme',
            ],
            'issue' => [
                'uuid' => '22222222-2222-4222-8222-222222222222',
                'title' => 'Boom',
                'level' => 'error',
            ],
        ];

        $teams = $formatter->httpRequest(
            NotificationDestinationType::Teams,
            'https://outlook.office.com/webhook/x',
            $payload,
            $destination,
        );

        $actions = $teams['json']['potentialAction'];
        self::assertCount(1, $actions);
        self::assertSame('Open in Beacon', $actions[0]['name']);
    }

    public function testEmailBodyIncludesFactsAndUrl(): void
    {
        $formatter = $this->formatter();
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
        $formatter = $this->formatter();
        $this->expectException(InvalidArgumentException::class);
        $formatter->parseTelegramEndpoint('not-valid');
    }

    public function testRejectsEmailDestinationsForHttpDelivery(): void
    {
        $formatter = $this->formatter();

        $this->expectException(InvalidArgumentException::class);
        $formatter->httpRequest(NotificationDestinationType::Email, 'mailto:test@example.com', []);
    }

    public function testRejectsUnsupportedDestinationTypesWhenNoFormatterClaimsThem(): void
    {
        $reflection = new ReflectionClass(NotificationOutboundFormatter::class);
        $formatter = $reflection->newInstanceWithoutConstructor();
        (new ReflectionProperty(NotificationOutboundFormatter::class, 'telegramFormatter'))
            ->setValue($formatter, new TelegramChannelFormatter());
        (new ReflectionProperty(NotificationOutboundFormatter::class, 'formatters'))
            ->setValue($formatter, []);

        $this->expectException(InvalidArgumentException::class);
        $formatter->httpRequest(NotificationDestinationType::Slack, 'https://example.test/hook', []);
    }
}
