<?php

declare(strict_types=1);

namespace App\Notifications\Formatter;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;

final class SlackChannelFormatter implements ChannelHttpFormatterInterface
{
    public function supports(NotificationDestinationType $type): bool
    {
        return NotificationDestinationType::Slack === $type;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{url: string, json: array<string, mixed>}
     */
    public function format(string $endpoint, array $payload, ?NotificationDestination $destination): array
    {
        $summary = (string) ($payload['summary'] ?? 'Beacon notification');

        return [
            'url' => $endpoint,
            'json' => $this->slackPayload($payload, $summary, $destination),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function slackPayload(array $payload, string $summary, ?NotificationDestination $destination): array
    {
        $json = [
            'text' => $summary,
            'attachments' => [$this->slackAttachment($payload, $summary)],
            'beacon' => $payload,
        ];

        $resolveBlock = $this->slackResolveActionsBlock($payload, $destination);
        if (null !== $resolveBlock) {
            $json['blocks'] = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $summary,
                    ],
                ],
                $resolveBlock,
            ];
        }

        return $json;
    }

    /**
     * Block Kit Resolve button when the Slack destination has a signing secret and the
     * payload identifies a real project/issue (not sample sends).
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function slackResolveActionsBlock(array $payload, ?NotificationDestination $destination): ?array
    {
        if (!$destination instanceof NotificationDestination || !$destination->hasSigningSecret()) {
            return null;
        }

        if (true === ($payload['test'] ?? false)) {
            return null;
        }

        $event = (string) ($payload['event'] ?? '');
        if (!\in_array($event, ['issue.new', 'issue.regression', 'issue.reopened'], true)) {
            return null;
        }

        $project = $payload['project'] ?? null;
        $issue = $payload['issue'] ?? null;
        if (!\is_array($project) || !\is_array($issue)) {
            return null;
        }

        $projectUuid = isset($project['uuid']) && \is_string($project['uuid']) ? $project['uuid'] : '';
        $issueUuid = isset($issue['uuid']) && \is_string($issue['uuid']) ? $issue['uuid'] : '';
        $destinationUuid = $destination->getUuid();
        if (\in_array('', [$projectUuid, $issueUuid, $destinationUuid], true)) {
            return null;
        }

        $valueResolve = json_encode([
            'a' => 'resolve',
            'd' => $destinationUuid,
            'p' => $projectUuid,
            'i' => $issueUuid,
        ], \JSON_THROW_ON_ERROR);
        $valueAssign = json_encode([
            'a' => 'assign',
            'd' => $destinationUuid,
            'p' => $projectUuid,
            'i' => $issueUuid,
        ], \JSON_THROW_ON_ERROR);

        return [
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'action_id' => 'beacon_resolve',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'Resolve',
                        'emoji' => false,
                    ],
                    'style' => 'primary',
                    'value' => $valueResolve,
                ],
                [
                    'type' => 'button',
                    'action_id' => 'beacon_assign',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'Assign to me',
                        'emoji' => false,
                    ],
                    'value' => $valueAssign,
                ],
            ],
        ];
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
            'fields' => OutboundPayloadFacts::factFields($payload),
            'footer' => true === ($payload['test'] ?? false) ? 'Beacon · sample send' : 'Beacon',
        ];

        if (isset($payload['url']) && \is_string($payload['url']) && '' !== $payload['url']) {
            $attachment['title_link'] = $payload['url'];
        }

        return $attachment;
    }
}
