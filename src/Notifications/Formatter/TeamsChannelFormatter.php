<?php

declare(strict_types=1);

namespace App\Notifications\Formatter;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\InteractionActionToken;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TeamsChannelFormatter implements ChannelHttpFormatterInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private InteractionActionToken $actionToken,
    ) {
    }

    public function supports(NotificationDestinationType $type): bool
    {
        return NotificationDestinationType::Teams === $type;
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
            'json' => $this->teamsMessageCard($payload, $summary, $destination),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function teamsMessageCard(array $payload, string $summary, ?NotificationDestination $destination): array
    {
        $facts = [];
        foreach (OutboundPayloadFacts::factFields($payload) as $field) {
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

        $actions = [];
        if (isset($payload['url']) && \is_string($payload['url']) && '' !== $payload['url']) {
            $actions[] = [
                '@type' => 'OpenUri',
                'name' => 'Open in Beacon',
                'targets' => [[
                    'os' => 'default',
                    'uri' => $payload['url'],
                ]],
            ];
        }

        $resolve = $this->teamsResolveHttpPost($payload, $destination);
        if (null !== $resolve) {
            $actions[] = $resolve;
        }

        $assign = $this->teamsAssignOpenUri($payload, $destination);
        if (null !== $assign) {
            $actions[] = $assign;
        }

        $card['potentialAction'] = $actions;

        return $card;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{projectUuid: string, issueUuid: string, destinationUuid: string}|null
     */
    private function teamsInteractiveContext(array $payload, ?NotificationDestination $destination): ?array
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

        return [
            'projectUuid' => $projectUuid,
            'issueUuid' => $issueUuid,
            'destinationUuid' => $destinationUuid,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function teamsResolveHttpPost(array $payload, ?NotificationDestination $destination): ?array
    {
        $ctx = $this->teamsInteractiveContext($payload, $destination);
        if (null === $ctx || !$destination instanceof NotificationDestination) {
            return null;
        }

        $token = $this->actionToken->issueResolveToken(
            (string) $destination->getSigningSecret(),
            $ctx['destinationUuid'],
            $ctx['projectUuid'],
            $ctx['issueUuid'],
        );

        $target = $this->urlGenerator->generate('hooks_teams_actions', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            '@type' => 'HttpPOST',
            'name' => 'Resolve',
            'target' => $target,
            'body' => json_encode($token, \JSON_THROW_ON_ERROR),
            'bodyContentType' => 'application/json',
            'headers' => [
                [
                    'name' => 'Content-Type',
                    'value' => 'application/json',
                ],
            ],
        ];
    }

    /**
     * OpenUri Assign: MessageCard HttpPOST cannot identify the clicker.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function teamsAssignOpenUri(array $payload, ?NotificationDestination $destination): ?array
    {
        $ctx = $this->teamsInteractiveContext($payload, $destination);
        if (null === $ctx || !$destination instanceof NotificationDestination) {
            return null;
        }

        $token = $this->actionToken->issueAssignToken(
            (string) $destination->getSigningSecret(),
            $ctx['destinationUuid'],
            $ctx['projectUuid'],
            $ctx['issueUuid'],
        );

        $uri = $this->urlGenerator->generate(
            'hooks_teams_assign_me',
            $token,
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return [
            '@type' => 'OpenUri',
            'name' => 'Assign to me',
            'targets' => [[
                'os' => 'default',
                'uri' => $uri,
            ]],
        ];
    }
}
