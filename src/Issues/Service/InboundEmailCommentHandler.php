<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Issues\Entity\InboundEmailMessage;
use App\Issues\Repository\InboundEmailMessageRepository;
use App\Issues\Repository\IssueRepository;
use App\Project\Service\ProjectAccessService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Turns a parsed inbound email into an issue comment (or no-op).
 */
final readonly class InboundEmailCommentHandler
{
    public function __construct(
        private InboundEmailReplyToken $replyToken,
        private InboundEmailQuoteStripper $quoteStripper,
        private IssueRepository $issueRepository,
        private UserRepository $userRepository,
        private ProjectAccessService $projectAccess,
        private IssueCommentCreator $commentCreator,
        private InboundEmailMessageRepository $inboundEmailMessageRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return 'created'|'duplicate'|'ignored'
     */
    public function handle(string $fromEmail, string $recipient, string $bodyPlain, ?string $messageId): string
    {
        $token = $this->extractTokenFromRecipient($recipient);
        if (null === $token) {
            $this->logger->info('Inbound email ignored: no reply token in recipient.', [
                'recipient' => $recipient,
            ]);

            return 'ignored';
        }

        $issueUuid = $this->replyToken->isValid($token);
        if (null === $issueUuid) {
            $this->logger->warning('Inbound email rejected: invalid reply token.');

            return 'ignored';
        }

        $normalizedMessageId = null !== $messageId && '' !== trim($messageId)
            ? trim($messageId)
            : null;
        if (null !== $normalizedMessageId
            && $this->inboundEmailMessageRepository->findOneByMessageId($normalizedMessageId) instanceof InboundEmailMessage
        ) {
            return 'duplicate';
        }

        $issue = $this->issueRepository->findOneBy(['uuid' => $issueUuid]);
        if (null === $issue || null === $issue->getProject()) {
            return 'ignored';
        }

        $user = $this->userRepository->findOneByEmail(trim(strtolower($fromEmail)));
        if (!$user instanceof User) {
            $this->logger->info('Inbound email ignored: unknown From address.', [
                'from' => $fromEmail,
            ]);

            return 'ignored';
        }

        try {
            $this->projectAccess->requireTriage($issue->getProject(), $user);
        } catch (AccessDeniedHttpException) {
            $this->logger->info('Inbound email ignored: no triage.', [
                'from' => $fromEmail,
                'issue_uuid' => $issueUuid,
            ]);

            return 'ignored';
        }

        $body = $this->quoteStripper->strip($bodyPlain);
        try {
            $comment = $this->commentCreator->create($issue, $user, $body, 'email');
        } catch (InvalidArgumentException) {
            return 'ignored';
        }

        if (null !== $normalizedMessageId) {
            $row = new InboundEmailMessage();
            $row->setMessageId($normalizedMessageId);
            $row->setCommentUuid($comment->getUuid());
            $this->entityManager->persist($row);
            $this->entityManager->flush();
        }

        return 'created';
    }

    private function extractTokenFromRecipient(string $recipient): ?string
    {
        $recipient = trim($recipient);
        if (preg_match('/^reply\+([^@]+)@/i', $recipient, $m)) {
            return $m[1];
        }
        if (preg_match('/reply\+([A-Za-z0-9_\-\.=]+)/', $recipient, $m)) {
            return $m[1];
        }

        return null;
    }
}
