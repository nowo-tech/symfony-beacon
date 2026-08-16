<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Repository\Query;

use App\Issues\Repository\Query\IssueReleaseQueryTrait;
use App\Project\Entity\Project;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IssueReleaseQueryTraitTest extends TestCase
{
    public function testReleaseQueriesSkipInvalidNormalizedRowsAndHandleEmptyProjects(): void
    {
        $project = new Project();
        new ReflectionProperty(Project::class, 'id')->setValue($project, 9);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnOnConsecutiveCalls(
            [' ', '1.0.0', '1.0.0'],
            ['', '2.0.0', '2.0.0'],
        );

        $arrayQuery = $this->createMock(Query::class);
        $arrayQuery->method('getArrayResult')->willReturn([
            ['release_value' => ' ', 'issue_count' => 99],
            ['release_value' => '3.0.0', 'issue_count' => 2],
        ]);

        $qb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();
        $qb->method('select')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($arrayQuery);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $repo = new IssueReleaseQueryTraitHarness($em, $qb);

        self::assertSame([], $repo->findDistinctFirstReleasesAcrossProjects([]));
        self::assertSame(['1.0.0'], $repo->findDistinctFirstReleasesAcrossProjects([$project], 10));
        self::assertSame(['2.0.0'], $repo->findDistinctReleases($project, 10));
        self::assertSame(['3.0.0' => 2], $repo->countNewIssuesByFirstReleaseMap($project));
    }
}

final class IssueReleaseQueryTraitHarness
{
    use IssueReleaseQueryTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private QueryBuilder $queryBuilder,
    ) {
    }

    protected function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->queryBuilder;
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
