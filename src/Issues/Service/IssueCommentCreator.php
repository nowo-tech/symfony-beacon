<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Issues\Entity\IssueMention;
use App\Notifications\Service\NotificationDispatcher;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Creates issue comments for UI and inbound email with shared side effects.
 */
final readonly class IssueCommentCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserActionRecorder $userActionRecorder,
        private NotificationDispatcher $notificationDispatcher,
        private IssueUserMailNotifier $issueUserMailNotifier,
        private IssueMentionParser $mentionParser,
    ) {
    }

    /**
     * @throws InvalidArgumentException When body is empty or too long
     */
    public function create(Issue $issue, User $author, string $body, string $via = 'ui'): IssueComment
    {
        $project = $issue->getProject();
        if (!$project instanceof Project) {
            throw new InvalidArgumentException('Issue has no project.');
        }

        $body = trim($body);
        if ('' === $body) {
            throw new InvalidArgumentException('empty');
        }
        if (mb_strlen($body) > IssueComment::BODY_MAX_LENGTH) {
            throw new InvalidArgumentException('too_long');
        }

        $comment = new IssueComment();
        $comment->setIssue($issue);
        $comment->setAuthor($author);
        $comment->setBody($body);
        $this->entityManager->persist($comment);
        $issue->addComment($comment);

        foreach ($this->mentionParser->resolveMentions($project, $body, $author) as $mentioned) {
            $mention = new IssueMention();
            $mention->setComment($comment);
            $mention->setMentionedUser($mentioned);
            $this->entityManager->persist($mention);
        }

        $this->userActionRecorder->record(
            UserActionType::IssueCommented,
            $author,
            $author,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
                'issue_uuid' => $issue->getUuid(),
                'issue_title' => $issue->getTitle(),
                'comment_uuid' => $comment->getUuid(),
                'via' => $via,
            ],
        );
        $this->notificationDispatcher->dispatchIssueCommented($project, $issue, $comment);
        $this->issueUserMailNotifier->notifyMentionsFromComment($project, $issue, $comment, $author);
        $this->entityManager->flush();

        return $comment;
    }
}
