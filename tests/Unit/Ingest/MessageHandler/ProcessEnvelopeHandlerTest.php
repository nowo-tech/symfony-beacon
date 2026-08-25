<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\MessageHandler;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\MessageHandler\ProcessEnvelopeHandler;
use App\Ingest\Service\EnvelopeParser;
use App\Issues\Entity\Event;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\EventPayloadPromoter;
use App\Issues\Service\EventTimestampParser;
use App\Issues\Service\FingerprintCalculator;
use App\Issues\Service\IssueEnvelopeWriter;
use App\Issues\Service\IssueHistoryRecorder;
use App\Performance\Service\NPlusOneDetector;
use App\Performance\Service\PerformanceEnvelopeWriter;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectGovernanceResolver;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Symfony\Component\Messenger\MessageBusInterface;

final class ProcessEnvelopeHandlerTest extends TestCase
{
    public function testReturnsWhenProjectMissingOrIngestBlocked(): void
    {
        self::expectNotToPerformAssertions();
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn(null);
        $this->handler($projects)(new ProcessEnvelopeMessage(1, "{\"sdk\":{}}\n", '2026-08-01T00:00:00+00:00'));

        $disabled = new Project()->setName('P')->setSlug('p')->setIngestEnabled(false);
        new ReflectionProperty(Project::class, 'id')->setValue($disabled, 2);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($disabled);
        $this->handler($projects)(new ProcessEnvelopeMessage(2, "{\"sdk\":{}}\n", '2026-08-01T00:00:00+00:00'));

        $quota = new Project()->setName('Q')->setSlug('q')->setEventQuotaDaily(1);
        new ReflectionProperty(Project::class, 'id')->setValue($quota, 3);
        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(1);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($quota);
        $this->handler($projects, events: $events)(new ProcessEnvelopeMessage(3, "{\"sdk\":{}}\n", '2026-08-01T00:00:00+00:00'));

        $monthly = new Project()->setName('M')->setSlug('m')->setEventQuotaMonthly(1);
        new ReflectionProperty(Project::class, 'id')->setValue($monthly, 4);
        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(0);
        $events->method('countReceivedSinceForProject')->willReturn(1);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($monthly);
        $this->handler($projects, events: $events)(new ProcessEnvelopeMessage(4, "{\"sdk\":{}}\n", '2026-08-01T00:00:00+00:00'));
    }

    public function testParsesItemsAndFlushesWithoutDispatch(): void
    {
        $project = new Project()->setName('Ok')->setSlug('ok');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 9);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($project);

        $flush = 0;
        $em = $this->entityManager(static function () use (&$flush): void {
            ++$flush;
        });
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $raw = "{\"sdk\":{}}\n".
            "{\"type\":\"session\"}\n".
            "{}\n".
            "{\"type\":\"event\"}\n".
            "not-json-array\n";

        $this->handler($projects, bus: $bus, em: $em)(
            new ProcessEnvelopeMessage(9, $raw, '2026-08-01T00:00:00+00:00'),
        );
        self::assertSame(1, $flush);
    }

    public function testSkipsEventItemsWhoseWriterReturnsSkippedResult(): void
    {
        $project = new Project()->setName('Ok')->setSlug('ok');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 10);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($project);

        $flush = 0;
        $em = $this->entityManager(static function () use (&$flush): void {
            ++$flush;
        });
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $issueEvents = $this->createStub(EventRepository::class);
        $issueEvents->method('findOneByProjectAndEventId')->willReturn(new Event());

        $raw = "{\"sdk\":{}}\n".
            "{\"type\":\"event\"}\n".
            "{\"event_id\":\"dup-1\",\"message\":\"skip me\"}\n";

        $this->handler($projects, bus: $bus, em: $em, issueEvents: $issueEvents)(
            new ProcessEnvelopeMessage(10, $raw, '2026-08-01T00:00:00+00:00'),
        );
        self::assertSame(1, $flush);
    }

    public function testRetriesOnceAfterUniqueConstraintRaceThenSucceeds(): void
    {
        $project = new Project()->setName('Race')->setSlug('race');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 11);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($project);

        $flush = 0;
        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getIdentityMap')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->expects(self::atLeastOnce())->method('clear');
        $em->method('flush')->willReturnCallback(function () use (&$flush): void {
            ++$flush;
            if (1 === $flush) {
                throw new UniqueConstraintViolationException(
                    $this->createStub(\Doctrine\DBAL\Driver\Exception::class),
                    null,
                );
            }
        });

        $raw = "{\"sdk\":{}}\n".
            "{\"type\":\"session\"}\n".
            "{}\n";

        $this->handler($projects, em: $em)(
            new ProcessEnvelopeMessage(11, $raw, '2026-08-01T00:00:00+00:00'),
        );
        self::assertSame(2, $flush);
    }

    public function testRethrowsUniqueConstraintOnSecondFailure(): void
    {
        $project = new Project()->setName('Race2')->setSlug('race2');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 12);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($project);

        $driverEx = $this->createStub(\Doctrine\DBAL\Driver\Exception::class);
        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getIdentityMap')->willReturn([]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('clear');
        $em->method('flush')->willThrowException(new UniqueConstraintViolationException($driverEx, null));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->handler($projects, em: $em)(
            new ProcessEnvelopeMessage(12, "{\"sdk\":{}}\n", '2026-08-01T00:00:00+00:00'),
        );
    }

    private function handler(
        ProjectRepository $projects,
        ?MessageBusInterface $bus = null,
        ?EntityManagerInterface $em = null,
        ?EventRepository $events = null,
        ?EventRepository $issueEvents = null,
    ): ProcessEnvelopeHandler {
        $bus ??= $this->createStub(MessageBusInterface::class);
        $em ??= $this->entityManager();
        if (null === $events) {
            $events = $this->createStub(EventRepository::class);
            $events->method('countReceivedTodayForProject')->willReturn(0);
            $events->method('countReceivedSinceForProject')->willReturn(0);
        }

        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $governance = new ProjectGovernanceResolver($events, new InstanceOpsDefaults($settingsRepo));

        $stats = $this->createStub(DailyProjectStatRepository::class);
        $issueWriter = new IssueEnvelopeWriter(
            new FingerprintCalculator(),
            new EventTimestampParser(),
            new EventPayloadPromoter(),
            $this->createStub(IssueRepository::class),
            $issueEvents ?? $this->createStub(EventRepository::class),
            $stats,
            new IssueHistoryRecorder($em),
            $em,
        );
        $perfWriter = new PerformanceEnvelopeWriter(new NPlusOneDetector(), $stats, $em);

        return new ProcessEnvelopeHandler(
            new EnvelopeParser(),
            $projects,
            $issueWriter,
            $perfWriter,
            $bus,
            $em,
            $governance,
            new NullLogger(),
        );
    }

    /**
     * @param callable():void|null $onFlush
     */
    private function entityManager(?callable $onFlush = null): EntityManagerInterface
    {
        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getIdentityMap')->willReturn([]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        if (null !== $onFlush) {
            $em->method('flush')->willReturnCallback($onFlush);
        }

        return $em;
    }
}
