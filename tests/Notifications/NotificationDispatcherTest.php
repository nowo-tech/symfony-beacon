<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Issues\Entity\Issue;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Message\DeliverNotificationMessage;
use App\Notifications\NotificationCategories;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use App\Shared\IssueStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotificationDispatcherTest extends TestCase
{
    public function testDispatchesOnlyMatchingEnabledDestinationsForNewIssue(): void
    {
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');

        $errorDest = $this->destination($project, ['error'], true);
        $warningDest = $this->destination($project, ['warning'], true);
        $disabled = $this->destination($project, ['error'], false);

        $repo = $this->createStub(NotificationDestinationRepository::class);
        $repo->method('findEnabledByProject')->willReturn([$errorDest, $warningDest]);

        $bus = $this->createMock(MessageBusInterface::class);
        $dispatched = [];
        $bus->expects(self::atLeastOnce())->method('dispatch')->willReturnCallback(
            static function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            },
        );

        $realtime = $this->createMock(MemberIssueRealtimeNotifierInterface::class);
        $realtime->expects(self::once())->method('notifyNewIssue');

        $dispatcher = $this->dispatcher($repo, $bus, $realtime);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setTitle('Boom');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setFingerprint('abc');

        $dispatcher->dispatchNewIssue($project, $issue);

        self::assertCount(1, $dispatched);
        self::assertInstanceOf(DeliverNotificationMessage::class, $dispatched[0]);
        self::assertSame(1, $dispatched[0]->destinationId);
        self::assertSame('issue.new', $dispatched[0]->payload['event']);
        self::assertNotContains($disabled, [$errorDest, $warningDest]); // sanity
    }

    public function testDispatchesLifecycleOnlyWhenCategoryEnabled(): void
    {
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');

        $resolvedDest = $this->destination($project, [NotificationCategories::ISSUE_RESOLVED], true);
        $assignedOnly = $this->destination($project, [NotificationCategories::ISSUE_ASSIGNED], true);

        $repo = $this->createStub(NotificationDestinationRepository::class);
        $repo->method('findEnabledByProject')->willReturn([$resolvedDest, $assignedOnly]);

        $bus = $this->createMock(MessageBusInterface::class);
        $dispatched = [];
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            },
        );

        $dispatcher = $this->dispatcher($repo, $bus);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setTitle('Boom');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Resolved);
        $issue->setFingerprint('abc');

        $dispatcher->dispatchIssueResolved($project, $issue);

        self::assertCount(1, $dispatched);
        self::assertInstanceOf(DeliverNotificationMessage::class, $dispatched[0]);
        self::assertSame($resolvedDest->getId(), $dispatched[0]->destinationId);
        self::assertSame('issue.resolved', $dispatched[0]->payload['event']);
        self::assertSame(NotificationCategories::ISSUE_RESOLVED, $dispatched[0]->payload['category']);
    }

    public function testNPlusOneRequiresCategory(): void
    {
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');

        $dest = $this->destination($project, [NotificationCategories::N_PLUS_ONE], true);
        $repo = $this->createStub(NotificationDestinationRepository::class);
        $repo->method('findEnabledByProject')->willReturn([$dest]);

        $bus = $this->createMock(MessageBusInterface::class);
        $count = 0;
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $message) use (&$count): Envelope {
                ++$count;

                return new Envelope($message);
            },
        );

        $dispatcher = $this->dispatcher($repo, $bus);

        $tx = new PerfTransaction();
        $tx->setProject($project);
        $tx->setTransactionName('GET /api');
        $tx->setNPlusOneCount(2);
        $tx->setSpanCount(10);
        $tx->setEventId('tx1');

        $dispatcher->dispatchNPlusOne($project, $tx);
        self::assertSame(1, $count);
    }

    private function dispatcher(
        NotificationDestinationRepository $repo,
        MessageBusInterface $bus,
        ?MemberIssueRealtimeNotifierInterface $realtime = null,
    ): NotificationDispatcher {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/issue');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('flush');

        return new NotificationDispatcher(
            $repo,
            $this->createStub(NotificationDigestBufferRepository::class),
            new NotificationPayloadBuilder($urls),
            new QuietHoursEvaluator(),
            $bus,
            $em,
            $realtime ?? $this->createStub(MemberIssueRealtimeNotifierInterface::class),
        );
    }

    /**
     * @param list<string> $categories
     */
    private function destination(Project $project, array $categories, bool $enabled): NotificationDestination
    {
        static $id = 0;
        ++$id;

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('D'.$id);
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hook');
        $destination->setEnabled($enabled);
        $destination->setCategories($categories);

        $ref = new ReflectionProperty($destination, 'id');
        $ref->setValue($destination, $id);

        return $destination;
    }
}
