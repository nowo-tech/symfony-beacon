<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueStatusChanger;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Project\Entity\Project;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class IssueStatusChangerTest extends TestCase
{
    public function testReturnsFalseWithoutProject(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $changed = $this->changer($em)->change(new Issue(), IssueStatus::Resolved, null);

        self::assertFalse($changed);
    }

    public function testReturnsFalseWhenStatusUnchanged(): void
    {
        $issue = $this->issue(IssueStatus::Unresolved);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        self::assertFalse($this->changer($em)->change($issue, IssueStatus::Unresolved, null));
    }

    public function testChangesStatusAndFlushes(): void
    {
        $actor = new User();
        $actor->setEmail('actor@example.com');
        $actor->setDisplayName('Actor');

        $issue = $this->issue(IssueStatus::Unresolved);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $changed = $this->changer($em)->change($issue, IssueStatus::Ignored, $actor, 'api');

        self::assertTrue($changed);
        self::assertSame(IssueStatus::Ignored, $issue->getStatus());
    }

    public function testResolvedDispatchesWithoutError(): void
    {
        $issue = $this->issue(IssueStatus::Unresolved);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('flush');

        self::assertTrue($this->changer($em)->change($issue, IssueStatus::Resolved, null));
        self::assertSame(IssueStatus::Resolved, $issue->getStatus());
    }

    public function testReopenFromResolvedDispatchesWithoutError(): void
    {
        $issue = $this->issue(IssueStatus::Resolved);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('flush');

        self::assertTrue($this->changer($em)->change($issue, IssueStatus::Unresolved, null));
        self::assertSame(IssueStatus::Unresolved, $issue->getStatus());
    }

    private function changer(EntityManagerInterface $em): IssueStatusChanger
    {
        return new IssueStatusChanger(
            $em,
            new IssueHistoryRecorder($em),
            new UserActionRecorder($em, new RequestStack()),
            $this->dispatcher($em),
        );
    }

    private function dispatcher(EntityManagerInterface $em): NotificationDispatcher
    {
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findEnabledByProject')->willReturn([]);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/issue');

        $settings = $this->createStub(InstanceSettingsRepository::class);
        $settings->method('getOrCreate')->willReturn(InstanceSettings::defaults());

        return new NotificationDispatcher(
            $destinations,
            $this->createStub(NotificationDigestBufferRepository::class),
            new NotificationPayloadBuilder($urls),
            new QuietHoursEvaluator(),
            new NotificationCircuitBreaker(new InstanceOpsDefaults($settings)),
            $this->createStub(MessageBusInterface::class),
            $em,
            $this->createStub(MemberIssueRealtimeNotifierInterface::class),
        );
    }

    private function issue(IssueStatus $status): Issue
    {
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setTitle('Boom');
        $issue->setLevel('error');
        $issue->setFingerprint('fp-1');
        $issue->setStatus($status);

        return $issue;
    }
}
