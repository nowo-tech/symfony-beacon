<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\IssueRepository;
use App\Notifications\Service\NotificationDispatcher;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Marks an issue as duplicate of a canonical issue, optionally merging events.
 *
 * @phpstan-type DuplicateResult array{
 *     ok: bool,
 *     flash: string,
 *     redirect_issue_uuid: string|null
 * }
 */
final readonly class IssueDuplicateMarker
{
    public function __construct(
        private IssueRepository $issueRepository,
        private IssueMergeService $issueMergeService,
        private IssueHistoryRecorder $historyRecorder,
        private UserActionRecorder $userActionRecorder,
        private NotificationDispatcher $notificationDispatcher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return DuplicateResult
     */
    public function mark(
        Project $project,
        Issue $issue,
        User $actor,
        string $canonicalUuid,
        bool $mergeEvents,
    ): array {
        $canonicalUuid = trim($canonicalUuid);
        if ('' === $canonicalUuid) {
            return $this->fail('issues.duplicate_invalid');
        }

        if ($canonicalUuid === $issue->getUuid()) {
            return $this->fail('issues.duplicate_self');
        }

        $canonical = $this->issueRepository->findOneByProjectAndUuid($project, $canonicalUuid);
        if (!$canonical instanceof Issue) {
            return $this->fail('issues.duplicate_not_found');
        }

        try {
            $this->issueMergeService->assertCanMarkAsDuplicate($issue, $canonical);
        } catch (InvalidArgumentException $e) {
            $flash = match ($e->getMessage()) {
                'circular' => 'issues.duplicate_circular',
                'wrong_project' => 'issues.duplicate_not_found',
                default => 'issues.duplicate_invalid',
            };

            return $this->fail($flash);
        }

        if ($mergeEvents) {
            try {
                $moved = $this->issueMergeService->mergeIntoCanonical($issue, $canonical, $actor);
            } catch (InvalidArgumentException) {
                return $this->fail('issues.merge_failed');
            }

            $this->userActionRecorder->record(
                UserActionType::IssueMerged,
                $actor,
                $actor,
                [
                    'project_uuid' => $project->getUuid(),
                    'project_name' => $project->getName(),
                    'issue_uuid' => $issue->getUuid(),
                    'issue_title' => $issue->getTitle(),
                    'canonical_uuid' => $canonical->getUuid(),
                    'canonical_title' => $canonical->getTitle(),
                    'events_moved' => $moved,
                ],
            );
            $this->notificationDispatcher->dispatchIssueDuplicated($project, $issue, $canonical);

            return [
                'ok' => true,
                'flash' => 'issues.merge_saved',
                'redirect_issue_uuid' => $canonical->getUuid(),
            ];
        }

        $previousStatus = $issue->getStatus();
        $issue->setDuplicateOf($canonical);
        $issue->setStatus(IssueStatus::Ignored);
        if (IssueStatus::Ignored !== $previousStatus) {
            $this->historyRecorder->recordStatusChange($issue, $previousStatus, IssueStatus::Ignored, $actor);
        }

        $this->userActionRecorder->record(
            UserActionType::IssueMarkedDuplicate,
            $actor,
            $actor,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
                'issue_uuid' => $issue->getUuid(),
                'issue_title' => $issue->getTitle(),
                'canonical_uuid' => $canonical->getUuid(),
                'canonical_title' => $canonical->getTitle(),
            ],
        );
        $this->notificationDispatcher->dispatchIssueDuplicated($project, $issue, $canonical);
        $this->entityManager->flush();

        return [
            'ok' => true,
            'flash' => 'issues.duplicate_saved',
            'redirect_issue_uuid' => null,
        ];
    }

    /**
     * @return DuplicateResult
     */
    private function fail(string $flash): array
    {
        return [
            'ok' => false,
            'flash' => $flash,
            'redirect_issue_uuid' => null,
        ];
    }
}
