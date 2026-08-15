<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Service\InboundEmailReplyToken;
use App\Issues\Service\IssueAssigneeChanger;
use App\Issues\Service\IssueAssigneeGuard;
use App\Issues\Service\IssueHistoryRecorder;
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
use App\Tests\Support\ProjectAccessServiceFactory;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class IssueAssigneeChangerTest extends TestCase
{
    public function testReturnsFalseWithoutProject(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $actor = $this->user(1, 'actor@example.com');
        self::assertFalse($this->changer($em)->assign(new Issue(), null, $actor));
    }

    public function testReturnsFalseWhenAssigneeUnchanged(): void
    {
        $assignee = $this->user(2, 'assignee@example.com');
        $actor = $this->user(1, 'actor@example.com');
        $issue = $this->issue();
        $issue->setAssignee($assignee);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        self::assertFalse($this->changer($em)->assign($issue, $assignee, $actor));
    }

    public function testAssignsAndClearsAssignee(): void
    {
        $assignee = $this->user(2, 'assignee@example.com');
        $actor = $this->user(1, 'actor@example.com');
        $issue = $this->issue();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $changer = $this->changer($em);
        self::assertTrue($changer->assign($issue, $assignee, $actor));
        self::assertSame($assignee, $issue->getAssignee());

        self::assertTrue($changer->assign($issue, null, $actor));
        self::assertNull($issue->getAssignee());
    }

    public function testRejectsNonMemberAssigneeWhenNotAdmin(): void
    {
        $assignee = $this->user(2, 'outsider@example.com');
        $actor = $this->user(1, 'actor@example.com');
        $issue = $this->issue();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('assignee_not_member');

        $this->changer($em, adminAccess: false)->assign($issue, $assignee, $actor);
    }

    private function changer(EntityManagerInterface $em, bool $adminAccess = true): IssueAssigneeChanger
    {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn($adminAccess);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn(null);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);

        $guard = new IssueAssigneeGuard(ProjectAccessServiceFactory::create(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        ));

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
        $mailNotifier = new IssueUserMailNotifier(
            $mailTransport,
            $mentionParser,
            $this->createStub(TranslatorInterface::class),
            $urls,
            new NullLogger(),
            new InboundEmailReplyToken($ops),
            $ops,
        );

        return new IssueAssigneeChanger(
            $em,
            new IssueHistoryRecorder($em),
            $guard,
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
            $mailNotifier,
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

    private function user(int $id, string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName(explode('@', $email)[0]);

        $property = new ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }
}
