<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Issues\Entity\Issue;
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
     * @param array<string, int|string> $parameters
     */
    private function absoluteUrl(string $route, array $parameters): string
    {
        return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
