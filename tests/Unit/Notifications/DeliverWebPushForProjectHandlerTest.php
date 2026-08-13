<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Identity\Entity\User;
use App\Notifications\Entity\PushSubscription;
use App\Notifications\Message\DeliverWebPushForProjectMessage;
use App\Notifications\MessageHandler\DeliverWebPushForProjectHandler;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\WebPushClientFactory;
use App\Notifications\Service\WebPushEndpointGuard;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use RuntimeException;

final class DeliverWebPushForProjectHandlerTest extends TestCase
{
    public function testNoopsWhenNotConfiguredOrProjectMissingOrNoEligibleUsers(): void
    {
        $factory = new WebPushClientFactory('', '', 'mailto:ops@example.com');
        $this->handler(factory: $factory)(new DeliverWebPushForProjectMessage(1, ['event' => 'issue.new']));

        $factory = new WebPushClientFactory('public-key', 'private-key', 'mailto:ops@example.com');
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn(null);
        $this->handler(factory: $factory, projects: $projects)(
            new DeliverWebPushForProjectMessage(99, ['event' => 'issue.new'])
        );

        $project = $this->project(3);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($project);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn([$this->user(1), $this->user(2)]);
        $subs = $this->createMock(PushSubscriptionRepository::class);
        $subs->expects(self::never())->method('findForPushEnabledUsers');
        $this->handler(factory: $factory, projects: $projects, memberships: $memberships, subscriptions: $subs)(
            new DeliverWebPushForProjectMessage(3, ['event' => 'issue.new'], [])
        );
    }

    public function testFiltersEligibleUsersSendsAndRemovesStaleUnsafeOrExpired(): void
    {
        $project = $this->project(8);
        $keep = $this->user(1);
        $drop = $this->user(2);
        $safe = $this->subscription($keep, 'https://fcm.googleapis.com/fcm/send/ok', 10);
        $unsafe = $this->subscription($keep, 'https://evil.example/push', 11);
        $failing = $this->subscription($keep, 'https://fcm.googleapis.com/fcm/send/fail', 12);
        $expired = $this->subscription($keep, 'https://fcm.googleapis.com/fcm/send/exp', 13);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($project);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn([$keep, $drop]);
        $subs = $this->createStub(PushSubscriptionRepository::class);
        $subs->method('findForPushEnabledUsers')->willReturnCallback(
            static function (array $users) use ($safe, $unsafe, $failing, $expired): array {
                self::assertCount(1, $users);
                self::assertSame(1, $users[0]->getId());

                return [$safe, $unsafe, $failing, $expired];
            },
        );

        $reportOk = $this->createStub(MessageSentReport::class);
        $reportOk->method('isSubscriptionExpired')->willReturn(false);
        $reportExpired = $this->createStub(MessageSentReport::class);
        $reportExpired->method('isSubscriptionExpired')->willReturn(true);

        $webPush = $this->createMock(WebPush::class);
        $sendCalls = 0;
        $webPush->expects(self::exactly(3))->method('sendOneNotification')->willReturnCallback(
            static function () use (&$sendCalls, $reportOk, $reportExpired): MessageSentReport {
                ++$sendCalls;
                if (2 === $sendCalls) {
                    throw new RuntimeException('network');
                }

                return 1 === $sendCalls ? $reportOk : $reportExpired;
            },
        );

        $factory = $this->createMock(WebPushClientFactory::class);
        $factory->expects(self::once())->method('isConfigured')->willReturn(true);
        $factory->expects(self::once())->method('create')->willReturn($webPush);
        $factory->expects(self::exactly(3))->method('createSubscription')->willReturn(
            Subscription::create([
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/x',
                'keys' => ['p256dh' => 'p', 'auth' => 'a'],
                'contentEncoding' => 'aesgcm',
            ]),
        );

        $removed = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('remove')->willReturnCallback(
            static function (object $entity) use (&$removed): void {
                $removed[] = $entity;
            },
        );
        $em->expects(self::once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');

        $handler = $this->handler(
            factory: $factory,
            projects: $projects,
            memberships: $memberships,
            subscriptions: $subs,
            guard: new WebPushEndpointGuard(),
            em: $em,
            logger: $logger,
        );
        $handler(new DeliverWebPushForProjectMessage(8, ['event' => 'issue.new', 'summary' => 'x'], [1]));

        self::assertContains($unsafe, $removed);
        self::assertContains($expired, $removed);
        self::assertNotContains($safe, $removed);
        self::assertNotContains($failing, $removed);
    }

    public function testNoopsWhenNoSubscriptions(): void
    {
        $project = $this->project(1);
        $user = $this->user(1);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($project);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn([$user]);
        $subs = $this->createStub(PushSubscriptionRepository::class);
        $subs->method('findForPushEnabledUsers')->willReturn([]);
        $factory = $this->createMock(WebPushClientFactory::class);
        $factory->method('isConfigured')->willReturn(true);
        $factory->expects(self::never())->method('create');
        $em = $this->createStub(EntityManagerInterface::class);

        $this->handler(factory: $factory, projects: $projects, memberships: $memberships, subscriptions: $subs, em: $em)(
            new DeliverWebPushForProjectMessage(1, ['event' => 'issue.new'], null)
        );
    }

    private function handler(
        ?WebPushClientFactory $factory = null,
        ?ProjectRepository $projects = null,
        ?ProjectMembershipRepository $memberships = null,
        ?PushSubscriptionRepository $subscriptions = null,
        ?WebPushEndpointGuard $guard = null,
        ?EntityManagerInterface $em = null,
        ?LoggerInterface $logger = null,
    ): DeliverWebPushForProjectHandler {
        return new DeliverWebPushForProjectHandler(
            $projects ?? $this->createStub(ProjectRepository::class),
            $memberships ?? $this->createStub(ProjectMembershipRepository::class),
            $subscriptions ?? $this->createStub(PushSubscriptionRepository::class),
            $factory ?? new WebPushClientFactory('', '', 'mailto:ops@example.com'),
            $guard ?? new WebPushEndpointGuard(),
            $em ?? $this->createStub(EntityManagerInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    private function user(int $id): User
    {
        $user = new User();
        $user->setEmail(uniqid('push-', true).'@example.com');
        $user->setPassword('x');
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }

    private function project(int $id): Project
    {
        $project = new Project();
        $project->setName('P');
        $project->setSlug('p-'.$id);
        new ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }

    private function subscription(User $user, string $endpoint, int $id): PushSubscription
    {
        $sub = new PushSubscription($user);
        $sub->setSubscription($endpoint, 'p256', 'auth', 'aesgcm');
        new ReflectionProperty(PushSubscription::class, 'id')->setValue($sub, $id);

        return $sub;
    }
}
