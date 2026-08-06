<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Issues\Enum\IssueStatus;
use App\Issues\Service\InboundEmailReplyToken;
use App\Issues\Service\IssueCommentCreator;
use App\Issues\Service\IssueMentionParser;
use App\Issues\Service\IssueUserMailNotifier;
use App\Issues\Service\IssueUserMailTransport;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class IssueCommentCreatorTest extends TestCase
{
    public function testRejectsIssueWithoutProject(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Issue has no project.');

        $this->creator($em)->create(new Issue(), $this->author(), 'hello');
    }

    public function testRejectsEmptyBody(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty');

        $this->creator($em)->create($this->issue(), $this->author(), "  \n\t ");
    }

    public function testRejectsTooLongBody(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('too_long');

        $body = str_repeat('a', IssueComment::BODY_MAX_LENGTH + 1);
        $this->creator($em)->create($this->issue(), $this->author(), $body);
    }

    public function testCreatesCommentAndFlushes(): void
    {
        $issue = $this->issue();
        $author = $this->author();

        $em = $this->createMock(EntityManagerInterface::class);
        $persistedComment = false;
        $em->expects(self::atLeastOnce())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persistedComment): void {
                if ($entity instanceof IssueComment) {
                    $persistedComment = true;
                }
            },
        );
        $em->expects(self::atLeastOnce())->method('flush');

        $comment = $this->creator($em)->create($issue, $author, '  Looks good  ', 'email');

        self::assertTrue($persistedComment);
        self::assertSame('Looks good', $comment->getBody());
        self::assertSame($author, $comment->getAuthor());
        self::assertSame($issue, $comment->getIssue());
        self::assertCount(1, $issue->getComments());
    }

    private function creator(EntityManagerInterface $em): IssueCommentCreator
    {
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn([]);

        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findEnabledByProject')->willReturn([]);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/issue');

        $settings = $this->createStub(InstanceSettingsRepository::class);
        $settings->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settings);

        $mailTransport = $this->createStub(IssueUserMailTransport::class);
        $mailTransport->method('isAvailable')->willReturn(false);

        $mentionParser = new IssueMentionParser($memberships);

        return new IssueCommentCreator(
            $em,
            new UserActionRecorder($em, new RequestStack()),
            new NotificationDispatcher(
                $destinations,
                $this->createStub(NotificationDigestBufferRepository::class),
                new NotificationPayloadBuilder($urls),
                new QuietHoursEvaluator(),
                new NotificationCircuitBreaker($ops),
                $this->createStub(MessageBusInterface::class),
                $em,
                $this->createStub(MemberIssueRealtimeNotifierInterface::class),
            ),
            new IssueUserMailNotifier(
                $mailTransport,
                $mentionParser,
                $this->createStub(TranslatorInterface::class),
                $urls,
                new NullLogger(),
                new InboundEmailReplyToken($ops),
                $ops,
            ),
            $mentionParser,
        );
    }

    private function issue(): Issue
    {
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setTitle('Boom');
        $issue->setLevel('error');
        $issue->setFingerprint('fp-1');
        $issue->setStatus(IssueStatus::Unresolved);

        return $issue;
    }

    private function author(): User
    {
        $user = new User();
        $user->setEmail('author@example.com');
        $user->setDisplayName('Author');

        return $user;
    }
}
