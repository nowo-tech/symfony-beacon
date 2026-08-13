<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Notifications\Service\NotificationDispatcher;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies issue status changes with history, audit, and lifecycle notifications.
 */
final readonly class IssueStatusChanger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IssueHistoryRecorder $historyRecorder,
        private UserActionRecorder $userActionRecorder,
        private NotificationDispatcher $notificationDispatcher,
    ) {
    }

    /**
     * @return bool True when the status actually changed
     */
    public function change(Issue $issue, IssueStatus $next, ?User $actor, string $via = 'ui'): bool
    {
        $project = $issue->getProject();
        if (!$project instanceof Project) {
            return false;
        }

        $previous = $issue->getStatus();
        if ($previous === $next) {
            return false;
        }

        IssueStatusTransition::assertCanTransition($previous, $next);

        $issue->setStatus($next);
        $this->historyRecorder->recordStatusChange($issue, $previous, $next, $actor);
        $this->userActionRecorder->record(
            UserActionType::IssueStatusChanged,
            $actor,
            $actor,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
                'issue_uuid' => $issue->getUuid(),
                'issue_title' => $issue->getTitle(),
                'from' => $previous->value,
                'to' => $next->value,
                'via' => $via,
            ],
        );

        if (IssueStatus::Resolved === $next) {
            $this->notificationDispatcher->dispatchIssueResolved($project, $issue);
        } elseif (
            IssueStatus::Unresolved === $next
            && \in_array($previous, [IssueStatus::Resolved, IssueStatus::Ignored], true)
        ) {
            $this->notificationDispatcher->dispatchIssueReopened($project, $issue);
        }

        $this->entityManager->flush();

        return true;
    }
}
