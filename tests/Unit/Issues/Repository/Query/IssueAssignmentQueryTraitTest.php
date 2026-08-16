<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Repository\Query;

use App\Identity\Entity\User;
use App\Issues\AssignmentScope;
use App\Issues\Enum\IssuePriority;
use App\Issues\IssueListSort;
use App\Issues\Repository\Query\IssueAssignmentQueryTrait;
use App\Project\Entity\Project;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class IssueAssignmentQueryTraitTest extends TestCase
{
    public function testSearchAndCountReturnEarlyWithoutProjects(): void
    {
        $repo = new IssueAssignmentQueryTraitHarness($this->createMock(QueryBuilder::class));
        $viewer = new User();

        self::assertSame([], $repo->searchAssignments([], AssignmentScope::All, $viewer));
        self::assertSame(0, $repo->countAssignments([], AssignmentScope::All, $viewer));
    }

    public function testSearchAssignmentsAppliesPriorityAndOffset(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();
        $wheres = [];
        $params = [];
        $firstResult = null;

        $qb->method('andWhere')->willReturnCallback(static function (string $expr) use (&$wheres, $qb): QueryBuilder {
            $wheres[] = $expr;

            return $qb;
        });
        $qb->method('setParameter')->willReturnCallback(static function (string $key, mixed $value) use (&$params, $qb): QueryBuilder {
            $params[$key] = $value;

            return $qb;
        });
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnCallback(static function (int $value) use (&$firstResult, $qb): QueryBuilder {
            $firstResult = $value;

            return $qb;
        });
        $qb->method('getQuery')->willReturn($query);

        $repo = new IssueAssignmentQueryTraitHarness($qb);
        $repo->searchAssignments(
            [new Project()],
            AssignmentScope::All,
            new User(),
            priority: IssuePriority::High,
            sort: new IssueListSort('last_seen', 'desc'),
            limit: 10,
            offset: 3,
        );

        self::assertContains('i.priority = :priority', $wheres);
        self::assertSame(IssuePriority::High, $params['priority']);
        self::assertSame(3, $firstResult);
    }
}

final class IssueAssignmentQueryTraitHarness
{
    use IssueAssignmentQueryTrait;

    public function __construct(private QueryBuilder $queryBuilder)
    {
    }

    protected function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->queryBuilder;
    }

    protected function applyFullTextOrLikeQuery(QueryBuilder $qb, string $query): void
    {
    }

    protected function applySqlSort(QueryBuilder $qb, IssueListSort $sort): void
    {
    }
}
