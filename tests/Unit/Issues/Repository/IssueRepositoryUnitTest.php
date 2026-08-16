<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Repository;

use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IssueRepositoryUnitTest extends TestCase
{
    public function testFindSimilarIssuesReturnsEarlyForInvalidInputs(): void
    {
        $repo = $this->repository($this->queryBuilderReturning([]));

        self::assertSame([], $repo->findSimilarIssues(new Issue(), 5));

        $issue = new Issue()
            ->setProject(new Project()->setName('Demo')->setSlug('demo'))
            ->setTitle('   ');
        self::assertSame([], $repo->findSimilarIssues($issue, 5));
    }

    public function testFindSimilarIssuesFallsBackToShortTitleAndSortsByScoreThenRecency(): void
    {
        $project = new Project()->setName('Demo')->setSlug('demo');
        $current = new Issue()
            ->setProject($project)
            ->setTitle('AB')
            ->setStatus(IssueStatus::Unresolved)
            ->setLastSeen(new DateTimeImmutable('2026-08-16 12:00:00'));
        new ReflectionProperty(Issue::class, 'id')->setValue($current, 10);

        $exactOlder = new Issue()
            ->setProject($project)
            ->setTitle('AB')
            ->setStatus(IssueStatus::Resolved)
            ->setLastSeen(new DateTimeImmutable('2026-08-16 10:00:00'));
        new ReflectionProperty(Issue::class, 'id')->setValue($exactOlder, 11);

        $exactNewer = new Issue()
            ->setProject($project)
            ->setTitle('AB')
            ->setStatus(IssueStatus::Resolved)
            ->setLastSeen(new DateTimeImmutable('2026-08-16 11:00:00'));
        new ReflectionProperty(Issue::class, 'id')->setValue($exactNewer, 12);

        $partial = new Issue()
            ->setProject($project)
            ->setTitle('AB related')
            ->setStatus(IssueStatus::Resolved)
            ->setLastSeen(new DateTimeImmutable('2026-08-16 09:00:00'));
        new ReflectionProperty(Issue::class, 'id')->setValue($partial, 13);

        $repo = $this->repository($this->queryBuilderReturning([$partial, $exactOlder, $exactNewer]));

        self::assertSame(
            [$exactNewer, $exactOlder, $partial],
            $repo->findSimilarIssues($current, 3),
        );
    }

    private function repository(QueryBuilder $qb): IssueRepository
    {
        $repo = $this->getMockBuilder(IssueRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repo->method('createQueryBuilder')->with('i')->willReturn($qb);

        return $repo;
    }

    private function queryBuilderReturning(array $result): QueryBuilder
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($result);

        $qb = $this->createMock(QueryBuilder::class);
        foreach (['andWhere', 'setParameter', 'orderBy', 'setMaxResults'] as $method) {
            $qb->method($method)->willReturnSelf();
        }
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }
}
