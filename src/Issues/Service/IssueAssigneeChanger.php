<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Issue;
use App\Notifications\Service\NotificationDispatcher;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Applies issue assignee changes with history, audit, notifications, and mail.
 */
final readonly class IssueAssigneeChanger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IssueHistoryRecorder $historyRecorder,
        private IssueAssigneeGuard $assigneeGuard,
        private UserActionRecorder $userActionRecorder,
        private NotificationDispatcher $notificationDispatcher,
        private IssueUserMailNotifier $issueUserMailNotifier,
    ) {
    }

    /**
     * @return bool True when the assignee actually changed
     *
     * @throws InvalidArgumentException When the assignee is not a project member
     */
    public function assign(Issue $issue, ?User $assignee, User $actor, string $via = 'ui'): bool
    {
        $project = $issue->getProject();
        if (!$project instanceof Project) {
            return false;
        }

        $previous = $issue->getAssignee();
        if ($previous?->getId() === $assignee?->getId()) {
            return false;
        }

        $this->assigneeGuard->assertAssignable($project, $assignee);
        $issue->setAssignee($assignee);
        $this->historyRecorder->recordAssigneeChange($issue, $previous, $assignee, $actor);
        $this->userActionRecorder->record(
            UserActionType::IssueAssigned,
            $actor,
            $assignee ?? $actor,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
                'issue_uuid' => $issue->getUuid(),
                'issue_title' => $issue->getTitle(),
                'from' => $previous?->getDisplayName(),
                'to' => $assignee?->getDisplayName(),
                'via' => $via,
            ],
        );
        $this->notificationDispatcher->dispatchIssueAssigned(
            $project,
            $issue,
            $previous,
            $assignee,
        );
        $this->issueUserMailNotifier->notifyAssigneeChanged(
            $project,
            $issue,
            $previous,
            $assignee,
            $actor,
        );
        $this->entityManager->flush();

        return true;
    }
}
