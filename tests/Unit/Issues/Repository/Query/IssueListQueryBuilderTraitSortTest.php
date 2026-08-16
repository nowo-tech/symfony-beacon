<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Repository\Query;

use App\Issues\IssueListSort;
use App\Issues\Repository\Query\IssueListQueryBuilderTrait;
use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class IssueListQueryBuilderTraitSortTest extends TestCase
{
    public function testApplySqlSortCoversEntitySortBranches(): void
    {
        $harness = new IssueListQueryBuilderTraitSortHarness();

        $title = $this->createMock(QueryBuilder::class);
        $title->expects(self::once())->method('orderBy')->with('i.title', 'ASC')->willReturnSelf();
        $title->expects(self::once())->method('addOrderBy')->with('i.id', 'DESC')->willReturnSelf();
        $harness->apply($title, new IssueListSort('title', 'asc'));

        $level = $this->createMock(QueryBuilder::class);
        $level->expects(self::once())->method('orderBy')->with('i.level', 'DESC')->willReturnSelf();
        $level->expects(self::once())->method('addOrderBy')->with('i.lastSeen', 'DESC')->willReturnSelf();
        $harness->apply($level, new IssueListSort('level', 'desc'));

        $events = $this->createMock(QueryBuilder::class);
        $events->expects(self::once())->method('orderBy')->with('i.eventCount', 'ASC')->willReturnSelf();
        $events->expects(self::once())->method('addOrderBy')->with('i.lastSeen', 'DESC')->willReturnSelf();
        $harness->apply($events, new IssueListSort('events', 'asc'));

        $firstSeen = $this->createMock(QueryBuilder::class);
        $firstSeen->expects(self::once())->method('orderBy')->with('i.firstSeen', 'DESC')->willReturnSelf();
        $firstSeen->expects(self::once())->method('addOrderBy')->with('i.id', 'DESC')->willReturnSelf();
        $harness->apply($firstSeen, new IssueListSort('first_seen', 'desc'));

        $default = $this->createMock(QueryBuilder::class);
        $default->expects(self::once())->method('orderBy')->with('i.lastSeen', 'DESC')->willReturnSelf();
        $default->expects(self::once())->method('addOrderBy')->with('i.id', 'DESC')->willReturnSelf();
        $harness->apply($default, new IssueListSort('bogus', 'asc'));
    }

    public function testApplyOccurrenceSortFallsBackTo24HoursForUnknownField(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())->method('addSelect')->with(self::stringContains('occ_count'))->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('occSince', self::isInstanceOf(DateTimeImmutable::class))
            ->willReturnSelf();
        $qb->expects(self::once())->method('orderBy')->with('occ_count', 'DESC')->willReturnSelf();
        $qb->expects(self::exactly(2))->method('addOrderBy')->willReturnSelf();

        $method = new ReflectionMethod(IssueListQueryBuilderTraitSortHarness::class, 'applyOccurrence');
        $method->invoke(new IssueListQueryBuilderTraitSortHarness(), $qb, new IssueListSort('bogus', 'desc'));
    }
}

final class IssueListQueryBuilderTraitSortHarness
{
    use IssueListQueryBuilderTrait;

    public function apply(QueryBuilder $qb, IssueListSort $sort): void
    {
        $this->applySqlSort($qb, $sort);
    }
}
