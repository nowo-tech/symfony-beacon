<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Repository\Query;

use App\Issues\IssueListSort;
use App\Issues\Repository\Query\IssueListQueryBuilderTrait;
use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use LogicException;
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

        // Reach private applyOccurrenceSqlSort default branch (unknown occurrence field → 24h).
        $method = new ReflectionMethod(IssueListQueryBuilderTraitSortHarness::class, 'applyOccurrenceSqlSort');
        $method->invoke(new IssueListQueryBuilderTraitSortHarness(), $qb, new IssueListSort('bogus', 'desc'));
    }

    public function testApplyOccurrenceSortCoversNamedWindows(): void
    {
        foreach (['events_24h', 'events_7d', 'events_30d'] as $field) {
            $qb = $this->createMock(QueryBuilder::class);
            $qb->expects(self::once())->method('addSelect')->with(self::stringContains('occ_count'))->willReturnSelf();
            $qb->expects(self::once())->method('setParameter')->with('occSince', self::isInstanceOf(DateTimeImmutable::class))->willReturnSelf();
            $qb->expects(self::once())->method('orderBy')->with('occ_count', 'ASC')->willReturnSelf();
            $qb->expects(self::exactly(2))->method('addOrderBy')->willReturnSelf();

            $harness = new IssueListQueryBuilderTraitSortHarness();
            $harness->apply($qb, new IssueListSort($field, 'asc'));
        }
    }
}

final class IssueListQueryBuilderTraitSortHarness
{
    use IssueListQueryBuilderTrait;

    public function apply(QueryBuilder $qb, IssueListSort $sort): void
    {
        $this->applySqlSort($qb, $sort);
    }

    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        unset($alias, $indexBy);

        throw new LogicException('createQueryBuilder is not used by applySqlSort tests');
    }

    protected function applyFullTextOrLikeQuery(QueryBuilder $qb, string $query): void
    {
    }

    protected function applyTagFilter(QueryBuilder $qb, \App\Project\Entity\Project $project, ?string $tag): void
    {
    }

    protected function applyUrlFilter(QueryBuilder $qb, \App\Project\Entity\Project $project, ?string $url): void
    {
    }

    protected function applyUserFilter(QueryBuilder $qb, ?string $user): void
    {
    }
}
