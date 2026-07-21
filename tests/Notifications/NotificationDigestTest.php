<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Issues\Entity\Issue;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Entity\NotificationDigestBuffer;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Message\DeliverNotificationMessage;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\NotificationDigestFlusher;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Project\Entity\Project;
use App\Shared\IssueStatus;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotificationDigestTest extends TestCase
{
    public function testQuietHoursBuffersInsteadOfDispatch(): void
    {
        $project = $this->project();
        $destination = $this->destination($project);
        $destination->setQuietHoursEnabled(true);
        $destination->setQuietHoursTimezone('UTC');
        $destination->setQuietHoursStart('00:00');
        $destination->setQuietHoursEnd('23:59');
        $destination->setDigestEnabled(true);

        $repo = $this->createStub(NotificationDestinationRepository::class);
        $repo->method('findEnabledByProject')->willReturn([$destination]);

        $buffered = [];
        $bufferRepo = $this->createStub(NotificationDigestBufferRepository::class);
        $bufferRepo->method('buffer')->willReturnCallback(
            static function (NotificationDestination $dest, array $payload) use (&$buffered): NotificationDigestBuffer {
                $buffered[] = $payload;
                $row = new NotificationDigestBuffer();
                $row->setDestination($dest);
                $row->setPayload($payload);

                return $row;
            },
        );

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/issue');

        $dispatcher = new NotificationDispatcher(
            $repo,
            $bufferRepo,
            new NotificationPayloadBuilder($urls),
            new QuietHoursEvaluator(),
            $bus,
            $em,
            $this->createStub(MemberIssueRealtimeNotifierInterface::class),
        );

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setTitle('Boom');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setFingerprint('abc');

        $dispatcher->dispatchNewIssue($project, $issue);

        self::assertCount(1, $buffered);
        self::assertSame('issue.new', $buffered[0]['event']);
    }

    public function testFlushDigestSendsOneSummary(): void
    {
        $project = $this->project();
        $destination = $this->destination($project);
        $destination->setQuietHoursEnabled(true);
        $destination->setQuietHoursTimezone('UTC');
        $destination->setQuietHoursStart('23:00');
        $destination->setQuietHoursEnd('23:30');
        $destination->setDigestEnabled(true);

        $rows = [];
        for ($i = 0; $i < 3; ++$i) {
            $row = new NotificationDigestBuffer();
            $row->setDestination($destination);
            $row->setPayload(['event' => 'issue.new', 'summary' => 'Item '.$i, 'category' => 'error']);
            $rows[] = $row;
        }

        $bufferRepo = $this->createMock(NotificationDigestBufferRepository::class);
        $bufferRepo->expects(self::once())->method('findDestinationsWithBufferedItems')->willReturn([$destination]);
        $bufferRepo->expects(self::once())->method('findForDestination')->willReturn($rows);
        $bufferRepo->expects(self::once())->method('removeAll')->with($rows);

        $dispatched = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            },
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/settings');

        $flusher = new NotificationDigestFlusher(
            $bufferRepo,
            new QuietHoursEvaluator(),
            new NotificationPayloadBuilder($urls),
            $bus,
            $em,
        );

        $result = $flusher->flush(force: true);

        self::assertSame(1, $result['destinations']);
        self::assertSame(1, $result['messages']);
        self::assertCount(1, $dispatched);
        self::assertInstanceOf(DeliverNotificationMessage::class, $dispatched[0]);
        self::assertSame('notification.digest', $dispatched[0]->payload['event']);
        self::assertSame(3, $dispatched[0]->payload['held_count']);
    }

    public function testQuietHoursEvaluatorOvernightWindow(): void
    {
        $project = $this->project();
        $destination = $this->destination($project);
        $destination->setQuietHoursEnabled(true);
        $destination->setQuietHoursTimezone('UTC');
        $destination->setQuietHoursStart('22:00');
        $destination->setQuietHoursEnd('07:00');

        $evaluator = new QuietHoursEvaluator();
        self::assertTrue($evaluator->isQuietHoursActive(
            $destination,
            new DateTimeImmutable('2026-07-21 23:00:00', new DateTimeZone('UTC')),
        ));
        self::assertTrue($evaluator->isQuietHoursActive(
            $destination,
            new DateTimeImmutable('2026-07-21 06:30:00', new DateTimeZone('UTC')),
        ));
        self::assertFalse($evaluator->isQuietHoursActive(
            $destination,
            new DateTimeImmutable('2026-07-21 12:00:00', new DateTimeZone('UTC')),
        ));
    }

    private function project(): Project
    {
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');

        return $project;
    }

    private function destination(Project $project): NotificationDestination
    {
        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hook');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);

        $ref = new ReflectionProperty($destination, 'id');
        $ref->setValue($destination, 42);

        return $destination;
    }
}
