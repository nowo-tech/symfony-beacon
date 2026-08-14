<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Issues\Service\InboundEmailReplyToken;
use App\Issues\Service\IssueMentionParser;
use App\Issues\Service\IssueUserMailNotifier;
use App\Issues\Service\IssueUserMailTransport;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class IssueUserMailNotifierTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testMentionNotifySendsWithReplyToWhenInboundEnabled(): void
    {
        $sent = [];
        $notifier = $this->notifier($this->transport(true, $sent), $this->createStub(LoggerInterface::class), inbound: true, members: [
            $this->user(2, 'alice@example.com', 'Alice'),
        ]);

        $project = new Project()->setName('Demo');
        $issue = new Issue()->setTitle('Crash')->setProject($project);
        $author = $this->user(1, 'author@example.com', 'Author');
        $notifier->notifyMentionsFromComment(
            $project,
            $issue,
            new IssueComment()->setBody('ping @alice')->setAuthor($author)->setIssue($issue),
            $author,
        );

        self::assertCount(1, $sent);
        self::assertSame('issues.mail.mention_subject', $sent[0]->getSubject());
        self::assertNotEmpty($sent[0]->getReplyTo());
    }

    public function testAssigneeNotifyAndSkipBranches(): void
    {
        $sent = [];
        $notifier = $this->notifier($this->transport(true, $sent), $this->createStub(LoggerInterface::class), inbound: false);

        $project = new Project()->setName('Demo');
        $issue = new Issue()->setTitle('Crash')->setProject($project);
        $actor = $this->user(1, 'actor@example.com', 'Actor');
        $assignee = $this->user(2, 'assignee@example.com', 'Assignee');

        $notifier->notifyAssigneeChanged($project, $issue, null, null, $actor);
        $notifier->notifyAssigneeChanged($project, $issue, $assignee, $assignee, $actor);
        $notifier->notifyAssigneeChanged($project, $issue, null, $assignee, $assignee);
        self::assertSame([], $sent);

        $notifier->notifyAssigneeChanged($project, $issue, null, $assignee, $actor);
        self::assertCount(1, $sent);
        self::assertSame('issues.mail.assign_subject', $sent[0]->getSubject());
        self::assertSame([], $sent[0]->getReplyTo());
    }

    public function testUnavailableAndInvalidEmailAndSendFailure(): void
    {
        $sent = [];
        $notifier = $this->notifier($this->transport(false, $sent), $this->createStub(LoggerInterface::class), inbound: false, members: [
            $this->user(2, 'alice@example.com', 'Alice'),
        ]);
        $project = new Project();
        $issue = new Issue()->setTitle('x')->setProject($project);
        $author = $this->user(1, 'a@example.com', 'A');
        $notifier->notifyMentionsFromComment(
            $project,
            $issue,
            new IssueComment()->setBody('@alice')->setAuthor($author)->setIssue($issue),
            $author,
        );
        self::assertSame([], $sent);

        $transport = $this->createMock(IssueUserMailTransport::class);
        $transport->method('isAvailable')->willReturn(true);
        $transport->method('getFromAddress')->willReturn('beacon@example.com');
        $transport->expects(self::once())->method('send')->willThrowException(new RuntimeException('smtp down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $notifier = $this->notifier($transport, $logger, inbound: false);
        $notifier->notifyAssigneeChanged(
            $project,
            $issue,
            null,
            $this->user(3, 'ok@example.com', 'Ok'),
            $author,
        );

        $notifier->notifyAssigneeChanged(
            $project,
            $issue,
            null,
            $this->user(4, 'not-an-email', 'Bad'),
            $author,
        );
    }

    /**
     * @param list<Email> $sent
     */
    private function transport(bool $available, array &$sent): IssueUserMailTransport
    {
        $transport = $this->createStub(IssueUserMailTransport::class);
        $transport->method('isAvailable')->willReturn($available);
        $transport->method('getFromAddress')->willReturn('beacon@example.com');
        $transport->method('send')->willReturnCallback(static function (Email $email) use (&$sent): void {
            $sent[] = $email;
        });

        return $transport;
    }

    /**
     * @param list<User> $members
     */
    private function notifier(
        IssueUserMailTransport $transport,
        LoggerInterface $logger,
        bool $inbound,
        array $members = [],
    ): IssueUserMailNotifier {
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn($members);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/issue');
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);
        $ops = $this->opsDefaultsWith(static function ($settings) use ($inbound): void {
            $settings->setInboundEmailEnabled($inbound);
            if ($inbound) {
                $settings->setInboundMailDomain('inbound.example.com');
                $settings->setInboundWebhookSecret('secret');
            }
        });

        return new IssueUserMailNotifier(
            $transport,
            new IssueMentionParser($memberships),
            $translator,
            $urls,
            $logger,
            new InboundEmailReplyToken($ops),
            $ops,
        );
    }

    private function user(int $id, string $email, string $name): User
    {
        $user = new User()->setEmail($email)->setDisplayName($name);
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
