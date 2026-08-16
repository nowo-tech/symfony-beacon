<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Entity\NotificationDigestBuffer;
use App\Notifications\Message\DeliverNotificationMessage;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\NotificationDigestFlusher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotificationDigestFlusherTest extends TestCase
{
    public function testSkipsQuietHoursUnlessForced(): void
    {
        $destination = $this->destination(digest: false);
        $destination->setQuietHoursEnabled(true);
        $destination->setQuietHoursStart('00:00');
        $destination->setQuietHoursEnd('23:59');
        $destination->setQuietHoursTimezone('UTC');

        $buffer = $this->createStub(NotificationDigestBufferRepository::class);
        $buffer->method('findDestinationsWithBufferedItems')->willReturn([$destination]);
        $buffer->method('findForDestination')->willReturn([$this->row($destination, ['summary' => 'A'])]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $flusher = $this->flusher($buffer, $bus);
        $result = $flusher->flush(force: false);
        self::assertSame(1, $result['skipped_quiet']);
        self::assertSame(0, $result['messages']);
    }

    public function testFlushesDigestAndIndividualPayloads(): void
    {
        $digestDest = $this->destination(digest: true);
        new ReflectionProperty(NotificationDestination::class, 'id')->setValue($digestDest, 10);
        $individual = $this->destination(digest: false);
        new ReflectionProperty(NotificationDestination::class, 'id')->setValue($individual, 11);

        $removed = 0;
        $buffer = $this->createStub(NotificationDigestBufferRepository::class);
        $buffer->method('findDestinationsWithBufferedItems')->willReturn([$digestDest, $individual]);
        $buffer->method('findForDestination')->willReturnCallback(
            static function (NotificationDestination $d) use ($digestDest, $individual): array {
                if ($d === $digestDest) {
                    return [
                        new NotificationDigestBuffer()->setDestination($d)->setPayload(['summary' => 'One']),
                        new NotificationDigestBuffer()->setDestination($d)->setPayload(['summary' => 'Two']),
                    ];
                }

                return [
                    new NotificationDigestBuffer()->setDestination($individual)->setPayload(['summary' => 'Solo']),
                ];
            },
        );
        $buffer->method('removeAll')->willReturnCallback(static function () use (&$removed): void {
            ++$removed;
        });

        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        $flush = 0;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });

        $result = $this->flusher($buffer, $bus, $em)->flush(force: true);
        self::assertSame(2, $result['destinations']);
        self::assertSame(2, $result['messages']); // 1 digest + 1 individual
        self::assertSame(2, $removed);
        self::assertSame(1, $flush);
        self::assertContainsOnlyInstancesOf(DeliverNotificationMessage::class, $dispatched);
        self::assertSame('notification.digest', $dispatched[0]->payload['event'] ?? null);
    }

    public function testFlushSkipsEmptyRowsNullIdsAndDigestDestinationsWithoutProject(): void
    {
        $emptyRows = $this->destination(digest: false);
        new ReflectionProperty(NotificationDestination::class, 'id')->setValue($emptyRows, 20);

        $nullId = $this->destination(digest: false);

        $digestWithoutProject = (new NotificationDestination())
            ->setLabel('Digest')
            ->setDigestEnabled(true);
        new ReflectionProperty(NotificationDestination::class, 'id')->setValue($digestWithoutProject, 21);

        $buffer = $this->createStub(NotificationDigestBufferRepository::class);
        $buffer->method('findDestinationsWithBufferedItems')->willReturn([$emptyRows, $nullId, $digestWithoutProject]);
        $buffer->method('findForDestination')->willReturnCallback(
            fn (NotificationDestination $destination): array => match (true) {
                $destination === $emptyRows => [],
                $destination === $nullId => [$this->row($nullId, ['summary' => 'pending'])],
                default => [$this->row($digestWithoutProject, ['summary' => 'digest'])],
            },
        );

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $result = $this->flusher($buffer, $bus, $em)->flush(force: true);

        self::assertSame(2, $result['destinations']);
        self::assertSame(0, $result['messages']);
        self::assertSame(0, $result['skipped_quiet']);
    }

    private function flusher(
        NotificationDigestBufferRepository $buffer,
        MessageBusInterface $bus,
        ?EntityManagerInterface $em = null,
    ): NotificationDigestFlusher {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/p');

        return new NotificationDigestFlusher(
            $buffer,
            new QuietHoursEvaluator(),
            new NotificationPayloadBuilder($urls),
            $bus,
            $em ?? $this->createStub(EntityManagerInterface::class),
        );
    }

    private function destination(bool $digest): NotificationDestination
    {
        return new NotificationDestination()
            ->setProject(new Project()->setName('P')->setSlug('p'))
            ->setLabel('Alerts')
            ->setDigestEnabled($digest);
    }

    /** @param array<string, mixed> $payload */
    private function row(NotificationDestination $destination, array $payload): NotificationDigestBuffer
    {
        return new NotificationDigestBuffer()->setDestination($destination)->setPayload($payload);
    }
}
