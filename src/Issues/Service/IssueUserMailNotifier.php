<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Project\Entity\Project;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Personal email notify for issue mentions and assignee changes (instance Mailer).
 */
final readonly class IssueUserMailNotifier
{
    public function __construct(
        private IssueUserMailTransport $mailTransport,
        private IssueMentionParser $mentionParser,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private InboundEmailReplyToken $inboundEmailReplyToken,
        #[Autowire('%beacon.inbound_email.enabled%')]
        private bool $inboundEmailEnabled = false,
        #[Autowire('%beacon.inbound_email.mail_domain%')]
        private string $inboundMailDomain = '',
    ) {
    }

    public function notifyMentionsFromComment(Project $project, Issue $issue, IssueComment $comment, User $author): void
    {
        if (!$this->mailTransport->isAvailable()) {
            return;
        }

        $mentioned = $this->mentionParser->resolveMentions($project, $comment->getBody(), $author);
        foreach ($mentioned as $user) {
            $this->sendSafe($user, 'issues.mail.mention_subject', 'issues.mail.mention_body', $project, $issue, [
                '%author%' => $author->getDisplayName(),
            ]);
        }
    }

    public function notifyAssigneeChanged(Project $project, Issue $issue, ?User $previous, ?User $assignee, User $actor): void
    {
        if (!$assignee instanceof User) {
            return;
        }
        if ($previous?->getId() === $assignee->getId()) {
            return;
        }
        if (!$this->mailTransport->isAvailable()) {
            return;
        }
        if ($assignee->getId() === $actor->getId()) {
            return;
        }

        $this->sendSafe($assignee, 'issues.mail.assign_subject', 'issues.mail.assign_body', $project, $issue, [
            '%actor%' => $actor->getDisplayName(),
        ]);
    }

    /**
     * @param array<string, string> $extraParams
     */
    private function sendSafe(User $to, string $subjectKey, string $bodyKey, Project $project, Issue $issue, array $extraParams): void
    {
        $email = trim(strtolower($to->getEmail()));
        if ('' === $email || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $url = $this->urlGenerator->generate('issue_show', [
            'projectId' => $project->getUuid(),
            'id' => $issue->getUuid(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $params = array_merge([
            '%issue%' => $issue->getTitle(),
            '%project%' => $project->getName(),
            '%link%' => $url,
        ], $extraParams);

        try {
            $message = new Email()
                ->from($this->mailTransport->getFromAddress())
                ->to($email)
                ->subject($this->translator->trans($subjectKey, $params))
                ->text($this->translator->trans($bodyKey, $params));
            if ($this->inboundEmailEnabled && '' !== $this->inboundMailDomain) {
                $token = $this->inboundEmailReplyToken->issue($issue->getUuid());
                $message->replyTo('reply+'.$token.'@'.$this->inboundMailDomain);
            }
            $this->mailTransport->send($message);
        } catch (Throwable $e) {
            $this->logger->warning('Issue user mail notify failed.', [
                'to' => $email,
                'issue_uuid' => $issue->getUuid(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
