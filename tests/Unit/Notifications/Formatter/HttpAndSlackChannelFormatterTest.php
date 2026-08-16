<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Formatter;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Formatter\HttpChannelFormatter;
use App\Notifications\Formatter\SlackChannelFormatter;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;

final class HttpAndSlackChannelFormatterTest extends TestCase
{
    public function testHttpPassesPayloadThrough(): void
    {
        $formatter = new HttpChannelFormatter();
        self::assertTrue($formatter->supports(NotificationDestinationType::Http));
        self::assertFalse($formatter->supports(NotificationDestinationType::Slack));

        $payload = ['event' => 'issue.new', 'summary' => 'x'];
        self::assertSame(
            ['url' => 'https://example.com/hook', 'json' => $payload],
            $formatter->format('https://example.com/hook', $payload, null),
        );
    }

    public function testSlackFormatsAttachmentWithoutBlocksForSample(): void
    {
        $formatter = new SlackChannelFormatter();
        self::assertTrue($formatter->supports(NotificationDestinationType::Slack));

        $payload = [
            'event' => 'test',
            'summary' => 'Sample',
            'url' => 'https://beacon.test/i/1',
            'project' => ['name' => 'Acme'],
            'issue' => ['title' => 'Sample', 'level' => 'error'],
            'test' => true,
        ];
        $formatted = $formatter->format('https://hooks.slack.com/services/T/B/X', $payload, null);

        self::assertSame('Sample', $formatted['json']['text']);
        self::assertSame($payload, $formatted['json']['beacon']);
        self::assertSame('#C9A227', $formatted['json']['attachments'][0]['color']);
        self::assertSame('https://beacon.test/i/1', $formatted['json']['attachments'][0]['title_link']);
        self::assertArrayNotHasKey('blocks', $formatted['json']);
    }

    public function testSlackSkipsInteractiveBlocksForSamplePayloadEvenWithSigningSecret(): void
    {
        $formatted = (new SlackChannelFormatter())->format('https://hooks.slack.com/services/T/B/X', [
            'event' => 'issue.new',
            'summary' => 'Sample',
            'project' => ['uuid' => 'p-uuid'],
            'issue' => ['uuid' => 'i-uuid'],
            'test' => true,
        ], $this->slackDestinationWithSigningSecret());

        self::assertArrayNotHasKey('blocks', $formatted['json']);
    }

    public function testSlackSkipsInteractiveBlocksForUnsupportedEvent(): void
    {
        $formatted = (new SlackChannelFormatter())->format('https://hooks.slack.com/services/T/B/X', [
            'event' => 'issue.resolved',
            'summary' => 'Sample',
            'project' => ['uuid' => 'p-uuid'],
            'issue' => ['uuid' => 'i-uuid'],
        ], $this->slackDestinationWithSigningSecret());

        self::assertArrayNotHasKey('blocks', $formatted['json']);
    }

    public function testSlackSkipsInteractiveBlocksWhenProjectOrIssuePayloadIsInvalid(): void
    {
        $formatted = (new SlackChannelFormatter())->format('https://hooks.slack.com/services/T/B/X', [
            'event' => 'issue.new',
            'summary' => 'Sample',
            'project' => 'not-an-array',
            'issue' => 'not-an-array',
        ], $this->slackDestinationWithSigningSecret());

        self::assertArrayNotHasKey('blocks', $formatted['json']);
    }

    public function testSlackSkipsInteractiveBlocksWhenInteractiveUuidsAreMissing(): void
    {
        $formatted = (new SlackChannelFormatter())->format('https://hooks.slack.com/services/T/B/X', [
            'event' => 'issue.new',
            'summary' => 'Sample',
            'project' => [],
            'issue' => [],
        ], $this->slackDestinationWithSigningSecret());

        self::assertArrayNotHasKey('blocks', $formatted['json']);
    }

    private function slackDestinationWithSigningSecret(): NotificationDestination
    {
        return (new NotificationDestination())
            ->setProject(new Project()->setName('Acme')->setSlug('acme'))
            ->setLabel('Slack')
            ->setType(NotificationDestinationType::Slack)
            ->setEndpointUrl('https://hooks.slack.com/services/T/B/X')
            ->setSigningSecret('slack-secret');
    }
}
