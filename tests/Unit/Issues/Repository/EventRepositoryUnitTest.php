<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Repository;

use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EventRepositoryUnitTest extends TestCase
{
    public function testOccurrenceStatsSkipsRowsForUnknownIssueIds(): void
    {
        $issue = new Issue();
        new ReflectionProperty(Issue::class, 'id')->setValue($issue, 5);
        $issue->incrementEventCount();

        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn([
            ['issueId' => 999, 'c24' => 1, 'c7' => 1, 'c30' => 1],
            ['issueId' => 5, 'c24' => 2, 'c7' => 3, 'c30' => 4],
        ]);

        $repo = $this->repository($this->queryBuilderReturning($query));
        $stats = $repo->occurrenceStatsForIssues([$issue], new DateTimeImmutable('2026-08-16 12:00:00'));

        self::assertSame(2, $stats[5]->last24h);
        self::assertSame(3, $stats[5]->last7d);
        self::assertSame(4, $stats[5]->last30d);
    }

    public function testPublicHelpersHandleNormalizedAndMaterializedDates(): void
    {
        $project = new Project()->setName('Demo')->setSlug('demo');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 7);
        $materialized = new DateTimeImmutable('2026-08-16 10:00:00');
        $stringableDay = new StringableDateTime('2026-08-16 00:00:00');

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['   ', '1.2.3']);
        $connection->method('fetchAllAssociative')->willReturn([
            ['day_key' => '', 'cnt' => 9],
            ['day_key' => $stringableDay, 'cnt' => 4],
        ]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $singleQuery = $this->createMock(Query::class);
        $singleQuery->method('getSingleScalarResult')->willReturn($materialized);
        $manyQuery = $this->createMock(Query::class);
        $manyQuery->method('getArrayResult')->willReturn([
            ['projectId' => 7, 'lastAt' => null],
            ['projectId' => 8, 'lastAt' => $materialized],
        ]);

        $repo = $this->getMockBuilder(EventRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder', 'getEntityManager'])
            ->getMock();
        $repo->method('getEntityManager')->willReturn($em);
        $repo->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturnOnConsecutiveCalls(
                $this->queryBuilderReturning($singleQuery),
                $this->queryBuilderReturning($manyQuery),
            );

        self::assertSame(['1.2.3'], $repo->findDistinctReleaseVersions($project, 10));
        self::assertSame($materialized, $repo->findLastReceivedAtForProject($project));
        self::assertSame([8 => $materialized], $repo->findLastReceivedAtForProjectIds([7, 8]));
        self::assertSame(
            ['2026-08-16' => 4],
            $repo->countErrorsByDay($project, new DateTimeImmutable('2026-08-15'), new DateTimeImmutable('2026-08-16')),
        );
    }

    private function repository(?QueryBuilder $qb = null, ?EntityManagerInterface $em = null): EventRepository
    {
        $repo = $this->getMockBuilder(EventRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder', 'getEntityManager'])
            ->getMock();
        if ($qb instanceof QueryBuilder) {
            $repo->method('createQueryBuilder')->with('e')->willReturn($qb);
        }
        if ($em instanceof EntityManagerInterface) {
            $repo->method('getEntityManager')->willReturn($em);
        }

        return $repo;
    }

    private function queryBuilderReturning(Query $query): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);
        foreach (['select', 'addSelect', 'andWhere', 'setParameter', 'groupBy', 'orderBy', 'addOrderBy', 'setMaxResults', 'innerJoin'] as $method) {
            $qb->method($method)->willReturnSelf();
        }
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }
}

final class StringableDateTime extends DateTimeImmutable
{
    public function __toString(): string
    {
        return $this->format('Y-m-d');
    }
}
