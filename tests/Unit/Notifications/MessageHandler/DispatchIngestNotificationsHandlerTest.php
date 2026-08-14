<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\MessageHandler;

use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Notifications\Message\DispatchIngestNotificationsMessage;
use App\Notifications\MessageHandler\DispatchIngestNotificationsHandler;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Repository\ProjectThresholdRuleRepository;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Notifications\Service\VolumeThresholdEvaluator;
use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DispatchIngestNotificationsHandlerTest extends TestCase
{
    public function testReturnsWhenProjectMissing(): void
    {
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn(null);
        $this->handler($projects)(new DispatchIngestNotificationsMessage(1, [], [], '2026-08-01T00:00:00+00:00'));
        self::assertTrue(true);
    }

    public function testDispatchesAlertKindsAndVolumeContexts(): void
    {
        $project = new Project()->setName('P')->setSlug('p');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 7);
        $issue = new Issue()->setProject($project)->setTitle('T')->setFingerprint('fp');
        new ReflectionProperty(Issue::class, 'id')->setValue($issue, 11);
        $tx = new PerfTransaction();
        new ReflectionProperty(PerfTransaction::class, 'id')->setValue($tx, 22);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($project);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(static function (string $class, int $id) use ($issue, $tx): object|null {
            if (Issue::class === $class && 11 === $id) {
                return $issue;
            }
            if (PerfTransaction::class === $class && 22 === $id) {
                return $tx;
            }

            return null;
        });

        $dispatched = 0;
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            ++$dispatched;

            return new Envelope($message);
        });

        $handler = $this->handler($projects, $em, $bus);
        $handler(new DispatchIngestNotificationsMessage(
            7,
            [
                ['kind' => 'new', 'issue_id' => 11],
                ['kind' => 'regression', 'issue_id' => 11],
                ['kind' => 'nplus1', 'transaction_id' => 22],
                ['kind' => 'new', 'issue_id' => 'bad'],
                ['kind' => 'nplus1', 'transaction_id' => 999],
            ],
            [['prod', '1.0.0']],
            '2026-08-01T00:00:00+00:00',
        ));

        // Destinations empty → dispatcher does not enqueue DeliverNotificationMessage, volume similarly no-op.
        self::assertSame(0, $dispatched);
    }

    private function handler(
        ProjectRepository $projects,
        ?EntityManagerInterface $em = null,
        ?MessageBusInterface $bus = null,
    ): DispatchIngestNotificationsHandler {
        $em ??= $this->createStub(EntityManagerInterface::class);
        $bus ??= $this->createStub(MessageBusInterface::class);
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settingsRepo);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/i/1');
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findEnabledByProject')->willReturn([]);
        $dispatcher = new NotificationDispatcher(
            $destinations,
            $this->createStub(NotificationDigestBufferRepository::class),
            new NotificationPayloadBuilder($urls),
            new QuietHoursEvaluator(),
            new NotificationCircuitBreaker($ops),
            $bus,
            $em,
            $this->createStub(MemberIssueRealtimeNotifierInterface::class),
        );
        $rules = $this->createStub(ProjectThresholdRuleRepository::class);
        $rules->method('findEnabledByProject')->willReturn([]);
        $events = $this->createStub(EventRepository::class);
        $evaluator = new VolumeThresholdEvaluator($rules, $events, $dispatcher, $em);

        return new DispatchIngestNotificationsHandler($projects, $em, $dispatcher, $evaluator);
    }
}
