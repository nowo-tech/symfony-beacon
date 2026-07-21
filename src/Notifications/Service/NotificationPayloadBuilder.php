<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Notifications\NotificationCategories;
use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds stable notification payloads for Slack and generic HTTP webhooks.
 */
final readonly class NotificationPayloadBuilder
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forNewIssue(Project $project, Issue $issue): array
    {
        return $this->forIssue($project, $issue, 'issue.new', 'New issue');
    }

    /**
     * @return array<string, mixed>
     */
    public function forIssueRegression(Project $project, Issue $issue): array
    {
        return $this->forIssue($project, $issue, 'issue.regression', 'Issue regression');
    }

    /**
     * @return array<string, mixed>
     */
    public function forIssueResolved(Project $project, Issue $issue): array
    {
        return $this->forLifecycleIssue(
            $project,
            $issue,
            NotificationCategories::ISSUE_RESOLVED,
            'Issue resolved',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forIssueReopened(Project $project, Issue $issue): array
    {
        return $this->forLifecycleIssue(
            $project,
            $issue,
            NotificationCategories::ISSUE_REOPENED,
            'Issue reopened',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forIssueAssigned(Project $project, Issue $issue, ?User $previousAssignee, ?User $newAssignee): array
    {
        $payload = $this->forLifecycleIssue(
            $project,
            $issue,
            NotificationCategories::ISSUE_ASSIGNED,
            'Issue assigned',
        );
        $payload['assignee'] = [
            'previous' => $this->userRef($previousAssignee),
            'current' => $this->userRef($newAssignee),
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function forIssueCommented(Project $project, Issue $issue, IssueComment $comment): array
    {
        $payload = $this->forLifecycleIssue(
            $project,
            $issue,
            NotificationCategories::ISSUE_COMMENTED,
            'Issue commented',
        );
        $body = $comment->getBody();
        $payload['comment'] = [
            'uuid' => $comment->getUuid(),
            'author' => $this->userRef($comment->getAuthor()),
            'body_preview' => mb_substr($body, 0, 200),
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function forIssueDuplicated(Project $project, Issue $issue, Issue $canonical): array
    {
        $payload = $this->forLifecycleIssue(
            $project,
            $issue,
            NotificationCategories::ISSUE_DUPLICATED,
            'Issue marked duplicate',
        );
        $payload['canonical_issue'] = [
            'id' => $canonical->getId() ?? 0,
            'uuid' => $canonical->getUuid(),
            'title' => $canonical->getTitle(),
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function forNPlusOne(Project $project, PerfTransaction $transaction): array
    {
        $projectId = $project->getId() ?? 0;
        $txId = $transaction->getId() ?? 0;

        return [
            'event' => 'performance.n_plus_one',
            'summary' => \sprintf(
                'N+1 detected in %s (%d group(s))',
                $transaction->getTransactionName(),
                $transaction->getNPlusOneCount(),
            ),
            'project' => [
                'id' => $projectId,
                'uuid' => $project->getUuid(),
                'name' => $project->getName(),
                'slug' => $project->getSlug(),
            ],
            'transaction' => [
                'id' => $txId,
                'uuid' => $transaction->getUuid(),
                'name' => $transaction->getTransactionName(),
                'n_plus_one_count' => $transaction->getNPlusOneCount(),
                'span_count' => $transaction->getSpanCount(),
            ],
            'url' => $this->absoluteUrl('performance_show', [
                'projectId' => $project->getUuid(),
                'id' => $transaction->getUuid(),
            ]),
            'category' => NotificationCategories::N_PLUS_ONE,
            'test' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forTest(Project $project, string $destinationLabel): array
    {
        return [
            'event' => 'test',
            'summary' => \sprintf('Test notification from %s (%s)', $project->getName(), $destinationLabel),
            'project' => [
                'id' => $project->getId() ?? 0,
                'uuid' => $project->getUuid(),
                'name' => $project->getName(),
                'slug' => $project->getSlug(),
            ],
            'url' => $this->absoluteUrl('project_settings', ['id' => $project->getUuid()]),
            'category' => 'test',
            'test' => true,
        ];
    }

    /**
     * One summary message for held quiet-hours items.
     *
     * @param list<array<string, mixed>> $heldPayloads
     *
     * @return array<string, mixed>
     */
    public function forDigest(Project $project, string $destinationLabel, array $heldPayloads): array
    {
        $count = \count($heldPayloads);
        $lines = [];
        foreach (\array_slice($heldPayloads, 0, 20) as $item) {
            $summary = isset($item['summary']) ? (string) $item['summary'] : (string) ($item['event'] ?? 'event');
            $lines[] = '- '.$summary;
        }
        if ($count > 20) {
            $lines[] = \sprintf('- …and %d more', $count - 20);
        }

        $body = \sprintf(
            'Digest for %s (%s): %d held notification(s)',
            $project->getName(),
            $destinationLabel,
            $count,
        );
        if ([] !== $lines) {
            $body .= "\n".implode("\n", $lines);
        }

        return [
            'event' => 'notification.digest',
            'summary' => $body,
            'project' => [
                'id' => $project->getId() ?? 0,
                'uuid' => $project->getUuid(),
                'name' => $project->getName(),
                'slug' => $project->getSlug(),
            ],
            'held_count' => $count,
            'items' => array_map(static function (array $item): array {
                return [
                    'event' => $item['event'] ?? null,
                    'summary' => $item['summary'] ?? null,
                    'category' => $item['category'] ?? null,
                ];
            }, $heldPayloads),
            'url' => $this->absoluteUrl('project_settings', ['id' => $project->getUuid()]),
            'category' => 'digest',
            'test' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forLifecycleIssue(Project $project, Issue $issue, string $event, string $summaryPrefix): array
    {
        $payload = $this->forIssue($project, $issue, $event, $summaryPrefix);
        $payload['category'] = $event;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function forIssue(Project $project, Issue $issue, string $event, string $summaryPrefix): array
    {
        $projectId = $project->getId() ?? 0;
        $issueId = $issue->getId() ?? 0;

        return [
            'event' => $event,
            'summary' => \sprintf('%s: [%s] %s', $summaryPrefix, $issue->getLevel(), $issue->getTitle()),
            'project' => [
                'id' => $projectId,
                'uuid' => $project->getUuid(),
                'name' => $project->getName(),
                'slug' => $project->getSlug(),
            ],
            'issue' => [
                'id' => $issueId,
                'uuid' => $issue->getUuid(),
                'title' => $issue->getTitle(),
                'level' => $issue->getLevel(),
                'status' => $issue->getStatus()->value,
                'culprit' => $issue->getCulprit(),
            ],
            'url' => $this->absoluteUrl('issue_show', [
                'projectId' => $project->getUuid(),
                'id' => $issue->getUuid(),
            ]),
            'category' => $issue->getLevel(),
            'test' => false,
        ];
    }

    /**
     * @return array{id: int|null, uuid: string|null, display_name: string|null, email: string|null}|null
     */
    private function userRef(?User $user): ?array
    {
        if (!$user instanceof User) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'uuid' => $user->getUuid(),
            'display_name' => $user->getDisplayName(),
            'email' => $user->getEmail(),
        ];
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function absoluteUrl(string $route, array $parameters): string
    {
        return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
