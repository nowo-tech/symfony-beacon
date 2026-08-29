<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueMentionRepository;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Message\DeliverWebPushForProjectMessage;
use App\Notifications\Realtime\IssueRealtimeTopics;
use App\Notifications\Realtime\MemberIssueRealtimeNotifier;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Notifications\Service\MemberAlertPreferenceEvaluator;
use App\Notifications\Service\WebPushClientFactory;
use App\Notifications\Service\WebPushPresentation;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class MemberIssueRealtimeNotifierTest extends TestCase
{
    public function testPublishesMercureAndQueuesPushForEligibleUsers(): void
    {
        $project = $this->project(5);
        $issue = new Issue();
        $issue->setProject($project);
        $eligible = $this->user(11);
        $skipped = $this->user(12);
        $skipped->setMemberAlertsEnabled(false);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn([$eligible, $skipped]);

        $published = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$published): MockResponse {
            $published[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return new MockResponse('', ['http_code' => 200]);
        });
        $mercure = $this->enabledMercure($http);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::callback(
            static function (object $message): bool {
                self::assertInstanceOf(DeliverWebPushForProjectMessage::class, $message);
                self::assertSame(5, $message->projectId);
                self::assertSame([11], $message->eligibleUserIds);
                self::assertSame('Issue assigned', $message->payload['title'] ?? null);
                self::assertSame('issue.assigned', $message->payload['event'] ?? null);

                return true;
            },
        ))->willReturnCallback(static fn (object $m): Envelope => new Envelope($m));

        $notifier = new MemberIssueRealtimeNotifier(
            $mercure,
            new WebPushClientFactory('public-key', 'private-key', 'mailto:ops@example.com'),
            $this->allowAllEvaluator(),
            $memberships,
            $bus,
            $this->createStub(LoggerInterface::class),
            new WebPushPresentation(),
        );
        $notifier->notify(MemberAlertEvent::IssueAssigned, $project, $issue, ['summary' => 'hello']);

        self::assertNotEmpty($published);
        $body = urldecode((string) $published[0]['body']);
        self::assertStringContainsString(IssueRealtimeTopics::forUser($eligible->getUuid()), $body);
        self::assertStringContainsString('issue.assigned', $body);
    }

    public function testLogsMercureFailuresAndSkipsPushWhenNotConfigured(): void
    {
        $project = $this->project(7);
        $issue = new Issue();
        $user = $this->user(3);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn([$user]);

        $http = new MockHttpClient(static function (): never {
            throw new class('hub down') extends RuntimeException implements TransportExceptionInterface {};
        });
        $mercure = $this->enabledMercure($http);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Mercure publish failed for member alert.',
            self::arrayHasKey('exception'),
        );

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $notifier = new MemberIssueRealtimeNotifier(
            $mercure,
            new WebPushClientFactory('', '', 'mailto:ops@example.com'),
            $this->allowAllEvaluator(),
            $memberships,
            $bus,
            $logger,
            new WebPushPresentation(),
        );
        $notifier->notify(MemberAlertEvent::IssueNew, $project, $issue, []);
    }

    public function testSkipsPushWhenProjectIdMissing(): void
    {
        $project = $this->project(null);
        $issue = new Issue();
        $user = $this->user(1);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn([$user]);

        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled(false);
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);
        $mercure = new ConfiguredMercure($repo, '', '', '', new MercureHubUrlGuard());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $notifier = new MemberIssueRealtimeNotifier(
            $mercure,
            new WebPushClientFactory('public-key', 'private-key', 'mailto:ops@example.com'),
            $this->allowAllEvaluator(),
            $memberships,
            $bus,
            $this->createStub(LoggerInterface::class),
            new WebPushPresentation(),
        );
        $notifier->notify(MemberAlertEvent::IssueCommented, $project, $issue, []);
    }

    private function enabledMercure(MockHttpClient $http): ConfiguredMercure
    {
        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled(true);
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        return new ConfiguredMercure(
            $repo,
            'http://mercure.test/.well-known/mercure',
            'https://beacon.example/.well-known/mercure',
            '!ChangeThisMercureHubJWTSecretKey!',
            new MercureHubUrlGuard(),
            $http,
        );
    }

    private function allowAllEvaluator(): MemberAlertPreferenceEvaluator
    {
        $projectPrefRepo = $this->createStub(MemberProjectAlertPreferenceRepository::class);
        $projectPrefRepo->method('findOneByUserAndProject')->willReturn(null);
        $projectPrefRepo->method('findIndexedByUserIdsForProject')->willReturn([]);
        $accountRepo = $this->createStub(MemberAccountAlertEventRepository::class);
        $accountRepo->method('findOneByUserAndEvent')->willReturn(null);
        $accountRepo->method('findIndexedByUserIds')->willReturn([]);
        $projectEventRepo = $this->createStub(MemberProjectAlertEventRepository::class);
        $projectEventRepo->method('findOneByUserProjectAndEvent')->willReturn(null);
        $projectEventRepo->method('findIndexedByUserIdsForProject')->willReturn([]);
        $mentionRepo = $this->createStub(IssueMentionRepository::class);
        $mentionRepo->method('isUserMentionedOnIssue')->willReturn(false);
        $mentionRepo->method('findUserIdsMentionedOnIssue')->willReturn([]);

        return new MemberAlertPreferenceEvaluator(
            $projectPrefRepo,
            $accountRepo,
            $projectEventRepo,
            $mentionRepo,
        );
    }

    private function user(int $id): User
    {
        $user = new User();
        $user->setEmail(uniqid('rt-', true).'@example.com');
        $user->setPassword('x');
        $user->setMemberAlertsEnabled(true);
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }

    private function project(?int $id): Project
    {
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme-'.($id ?? 'x'));
        if (null !== $id) {
            new ReflectionProperty(Project::class, 'id')->setValue($project, $id);
        }

        return $project;
    }
}
