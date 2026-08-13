<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\EventTimestampParser;
use App\Issues\Service\FingerprintCalculator;
use App\Issues\Service\IssueEnvelopeWriter;
use App\Issues\Service\IssueHistoryRecorder;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IssueEnvelopeWriterTest extends TestCase
{
    private EventRepository&Stub $eventRepository;
    private IssueRepository&Stub $issueRepository;
    private EntityManagerInterface&Stub $entityManager;
    /** @var list<object> */
    private array $persisted = [];
    private DailyProjectStat $stat;
    private IssueEnvelopeWriter $writer;

    protected function setUp(): void
    {
        $this->persisted = [];
        $this->stat = new DailyProjectStat();
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->issueRepository = $this->createStub(IssueRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $stats = $this->createStub(DailyProjectStatRepository::class);
        $stats->method('findOrCreate')->willReturn($this->stat);

        $this->writer = new IssueEnvelopeWriter(
            new FingerprintCalculator(),
            new EventTimestampParser(),
            $this->issueRepository,
            $this->eventRepository,
            $stats,
            new IssueHistoryRecorder($this->entityManager),
            $this->entityManager,
        );
    }

    public function testSkipsDuplicateEventId(): void
    {
        $this->eventRepository->method('findOneByProjectAndEventId')->willReturn(new Event());
        $result = $this->writer->write(new Project(), ['event_id' => 'dup'], new DateTimeImmutable());
        self::assertTrue($result->skipped);
        self::assertSame([], $this->persisted);
    }

    public function testCreatesNewIssueAndEventWithContext(): void
    {
        $project = $this->project(1);
        $this->eventRepository->method('findOneByProjectAndEventId')->willReturn(null);
        $this->issueRepository->method('findOneByProjectAndFingerprint')->willReturn(null);
        $receivedAt = new DateTimeImmutable('2026-08-13T10:00:00+00:00');

        $result = $this->writer->write($project, [
            'event_id' => 'e1',
            'message' => 'Boom',
            'level' => 'error',
            'environment' => 'prod',
            'release' => '1.2.3',
            'platform' => 'php',
            'contexts' => [
                'runtime' => ['version' => '8.4.1'],
                'framework' => ['name' => 'symfony', 'version' => '7.3.0'],
            ],
            'user' => ['email' => 'u@example.com'],
            'timestamp' => $receivedAt->getTimestamp(),
        ], $receivedAt);

        self::assertFalse($result->skipped);
        self::assertTrue($result->isNew);
        self::assertFalse($result->isRegression);
        self::assertTrue($result->countsTowardVolumeThreshold);
        self::assertSame('prod', $result->environment);
        self::assertSame('1.2.3', $result->release);
        self::assertSame(1, $this->stat->getErrorCount());
        self::assertCount(2, $this->persisted); // issue + event
        self::assertInstanceOf(Issue::class, $result->issue);
        self::assertSame('1.2.3', $result->issue->getFirstRelease());
        self::assertSame('prod', $result->issue->getLastEnvironment());
    }

    public function testRegressionReopensResolvedIssue(): void
    {
        $project = $this->project(1);
        $existing = (new Issue())
            ->setProject($project)
            ->setFingerprint('fp')
            ->setTitle('Old')
            ->setStatus(IssueStatus::Resolved)
            ->setEventCount(2);
        (new ReflectionProperty(Issue::class, 'id'))->setValue($existing, 9);
        $this->eventRepository->method('findOneByProjectAndEventId')->willReturn(null);
        $this->issueRepository->method('findOneByProjectAndFingerprint')->willReturn($existing);

        $result = $this->writer->write($project, [
            'event_id' => 'e2',
            'fingerprint' => ['stable'],
            'level' => 'warning',
            'message' => 'again',
        ], new DateTimeImmutable());

        self::assertTrue($result->isRegression);
        self::assertFalse($result->isNew);
        self::assertFalse($result->countsTowardVolumeThreshold);
        self::assertSame(IssueStatus::Unresolved, $existing->getStatus());
        self::assertSame(3, $existing->getEventCount());
    }

    private function project(int $id): Project
    {
        $project = (new Project())->setName('P')->setSlug('p');
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, $id);

        return $project;
    }
}
