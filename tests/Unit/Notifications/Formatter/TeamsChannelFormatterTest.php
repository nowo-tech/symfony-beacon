<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Formatter;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Formatter\TeamsChannelFormatter;
use App\Notifications\Service\InteractionActionToken;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TeamsChannelFormatterTest extends TestCase
{
    public function testSupportsTeamsOnly(): void
    {
        $formatter = $this->formatter();
        self::assertTrue($formatter->supports(NotificationDestinationType::Teams));
        self::assertFalse($formatter->supports(NotificationDestinationType::Slack));
    }

    public function testFormatBuildsMessageCardWithoutInteractiveActionsForSample(): void
    {
        $formatted = $this->formatter()->format('https://outlook.office.com/webhook/x', [
            'event' => 'issue.new',
            'summary' => 'Boom',
            'url' => 'https://beacon.test/i/1',
            'project' => ['name' => 'Acme', 'uuid' => 'p-uuid'],
            'issue' => ['title' => 'Boom', 'level' => 'error', 'uuid' => 'i-uuid'],
            'test' => true,
        ], null);

        self::assertSame('https://outlook.office.com/webhook/x', $formatted['url']);
        $card = $formatted['json'];
        self::assertSame('MessageCard', $card['@type']);
        self::assertSame('C9A227', $card['themeColor']);
        self::assertSame('Boom', $card['summary']);
        self::assertCount(1, $card['potentialAction']);
        self::assertSame('Open in Beacon', $card['potentialAction'][0]['name']);
        self::assertSame('https://beacon.test/i/1', $card['potentialAction'][0]['targets'][0]['uri']);
    }

    public function testFormatAddsResolveAndAssignWhenDestinationHasSigningSecret(): void
    {
        $destination = (new NotificationDestination())
            ->setProject((new Project())->setName('Acme')->setSlug('acme'))
            ->setLabel('Teams')
            ->setType(NotificationDestinationType::Teams)
            ->setEndpointUrl('https://outlook.office.com/webhook/x')
            ->setSigningSecret('teams-secret');

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::exactly(2))->method('generate')->willReturnCallback(
            static function (string $route, array $params = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string {
                if ('hooks_teams_actions' === $route) {
                    return 'https://beacon.test/hooks/teams/actions';
                }
                if ('hooks_teams_assign_me' === $route) {
                    return 'https://beacon.test/hooks/teams/assign?'.http_build_query($params);
                }

                self::fail('Unexpected route: '.$route);
            },
        );

        $formatted = $this->formatter($urls)->format('https://outlook.office.com/webhook/x', [
            'event' => 'issue.new',
            'summary' => 'Boom',
            'url' => 'https://beacon.test/i/1',
            'project' => ['name' => 'Acme', 'uuid' => '11111111-1111-7111-8111-111111111111'],
            'issue' => ['title' => 'Boom', 'uuid' => '22222222-2222-7222-8222-222222222222'],
        ], $destination);

        $actions = $formatted['json']['potentialAction'];
        self::assertCount(3, $actions);
        self::assertSame('Open in Beacon', $actions[0]['name']);
        self::assertSame('HttpPOST', $actions[1]['@type']);
        self::assertSame('Resolve', $actions[1]['name']);
        self::assertSame('https://beacon.test/hooks/teams/actions', $actions[1]['target']);
        self::assertSame('OpenUri', $actions[2]['@type']);
        self::assertSame('Assign to me', $actions[2]['name']);
        self::assertStringContainsString('https://beacon.test/hooks/teams/assign?', $actions[2]['targets'][0]['uri']);
    }

    public function testFormatSkipsInteractiveActionsWithoutSigningSecret(): void
    {
        $destination = (new NotificationDestination())
            ->setProject((new Project())->setName('Acme')->setSlug('acme'))
            ->setLabel('Teams')
            ->setType(NotificationDestinationType::Teams)
            ->setEndpointUrl('https://outlook.office.com/webhook/x');

        $formatted = $this->formatter()->format('https://outlook.office.com/webhook/x', [
            'event' => 'issue.new',
            'summary' => 'Boom',
            'project' => ['uuid' => 'p'],
            'issue' => ['uuid' => 'i'],
        ], $destination);

        self::assertSame([], $formatted['json']['potentialAction']);
    }

    private function formatter(?UrlGeneratorInterface $urls = null): TeamsChannelFormatter
    {
        return new TeamsChannelFormatter(
            $urls ?? $this->createStub(UrlGeneratorInterface::class),
            new InteractionActionToken(),
        );
    }
}
