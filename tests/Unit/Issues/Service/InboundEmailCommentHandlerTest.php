<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Entity\InboundEmailMessage;
use App\Issues\Entity\Issue;
use App\Issues\Repository\InboundEmailMessageRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\InboundEmailCommentHandler;
use App\Issues\Service\InboundEmailQuoteStripper;
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
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class InboundEmailCommentHandlerTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testIgnoredWithoutTokenOrInvalidToken(): void
    {
        $handler = $this->handler();
        self::assertSame('ignored', $handler->handle('a@example.com', 'nobody@example.com', 'hi', null));
        self::assertSame('ignored', $handler->handle('a@example.com', 'reply+badtoken@example.com', 'hi', null));
    }

    public function testDuplicateMessageId(): void
    {
        $tokenSvc = new InboundEmailReplyToken($this->opsDefaultsWith(static function (InstanceSettings $s): void {
            $s->setInboundWebhookSecret('secret');
        }));
        $token = $tokenSvc->issue('issue-uuid', 'alice@example.com');

        $inbound = $this->createStub(InboundEmailMessageRepository::class);
        $inbound->method('findOneByMessageId')->willReturn(new InboundEmailMessage());

        $handler = $this->handler(replyToken: $tokenSvc, inboundRepo: $inbound);
        self::assertSame(
            'duplicate',
            $handler->handle('alice@example.com', 'reply+'.$token.'@beacon.test', 'body', 'mid-1'),
        );
    }

    public function testCreatedHappyPath(): void
    {
        $tokenSvc = new InboundEmailReplyToken($this->opsDefaultsWith(static function (InstanceSettings $s): void {
            $s->setInboundWebhookSecret('secret');
        }));
        $token = $tokenSvc->issue('issue-uuid', 'alice@example.com');

        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('fp');
        $issue->setTitle('Title');

        $user = new User();
        $user->setEmail('alice@example.com');
        $user->setDisplayName('Alice');

        $issueRepo = $this->createStub(IssueRepository::class);
        $issueRepo->method('findOneBy')->willReturn($issue);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findOneByEmail')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $handler = $this->handler(
            replyToken: $tokenSvc,
            issueRepo: $issueRepo,
            userRepo: $userRepo,
            em: $em,
            isAdmin: true,
        );

        self::assertSame(
            'created',
            $handler->handle('Alice@Example.com', 'reply+'.$token.'@beacon.test', "Hello\n\n> quote", 'mid-2'),
        );
    }

    public function testIgnoredWhenFromMismatchOrNoTriage(): void
    {
        $tokenSvc = new InboundEmailReplyToken($this->opsDefaultsWith(static function (InstanceSettings $s): void {
            $s->setInboundWebhookSecret('secret');
        }));
        $token = $tokenSvc->issue('issue-uuid', 'alice@example.com');

        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('fp');
        $issue->setTitle('Title');

        $issueRepo = $this->createStub(IssueRepository::class);
        $issueRepo->method('findOneBy')->willReturn($issue);

        $handler = $this->handler(replyToken: $tokenSvc, issueRepo: $issueRepo);
        self::assertSame(
            'ignored',
            $handler->handle('other@example.com', 'reply+'.$token.'@beacon.test', 'hi', null),
        );

        $user = new User();
        $user->setEmail('alice@example.com');
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findOneByEmail')->willReturn($user);

        $handler2 = $this->handler(
            replyToken: $tokenSvc,
            issueRepo: $issueRepo,
            userRepo: $userRepo,
            isAdmin: false,
        );
        self::assertSame(
            'ignored',
            $handler2->handle('alice@example.com', 'reply+'.$token.'@beacon.test', 'hi', null),
        );
    }

    private function handler(
        ?InboundEmailReplyToken $replyToken = null,
        ?IssueRepository $issueRepo = null,
        ?UserRepository $userRepo = null,
        ?InboundEmailMessageRepository $inboundRepo = null,
        ?EntityManagerInterface $em = null,
        bool $isAdmin = true,
    ): InboundEmailCommentHandler {
        $em ??= $this->createStub(EntityManagerInterface::class);
        $replyToken ??= new InboundEmailReplyToken($this->opsDefaultsWith(static function (InstanceSettings $s): void {
            $s->setInboundWebhookSecret('secret');
        }));

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn($isAdmin);

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

        $commentCreator = new IssueCommentCreator(
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
                $replyToken,
                $ops,
            ),
            $mentionParser,
        );

        return new InboundEmailCommentHandler(
            $replyToken,
            new InboundEmailQuoteStripper(),
            $issueRepo ?? $this->createStub(IssueRepository::class),
            $userRepo ?? $this->createStub(UserRepository::class),
            new ProjectAccessService(
                $memberships,
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $auth,
                new RequestStack(),
            ),
            $commentCreator,
            $inboundRepo ?? $this->createStub(InboundEmailMessageRepository::class),
            $em,
            new NullLogger(),
        );
    }
}
