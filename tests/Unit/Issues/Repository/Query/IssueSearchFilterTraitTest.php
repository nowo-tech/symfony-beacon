<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Repository\Query;

use App\Issues\Repository\EventTagRepository;
use App\Issues\Repository\Query\IssueSearchFilterTrait;
use App\Project\Entity\Project;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class IssueSearchFilterTraitTest extends TestCase
{
    public function testApplyFullTextFallsBackToLikeForNonMysqlAndEmptyBooleanQuery(): void
    {
        $nonMysql = $this->repositoryWithPlatform(new SQLitePlatform());
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with("i.title LIKE :q ESCAPE '\\' OR i.culprit LIKE :q ESCAPE '\\'")
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('q', '%error\\_rate%')
            ->willReturnSelf();
        $nonMysql->applyFullText($qb, 'error_rate');

        $mysql = $this->repositoryWithPlatform($this->createMock(AbstractMySQLPlatform::class));
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with("i.title LIKE :q ESCAPE '\\' OR i.culprit LIKE :q ESCAPE '\\'")
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('q', '%a + -%')
            ->willReturnSelf();
        $mysql->applyFullText($qb, 'a + -');
    }

    public function testApplyFullTextUsesMysqlBooleanSearchAndRestrictsToIds(): void
    {
        $platform = $this->createMock(AbstractMySQLPlatform::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects(self::once())
            ->method('fetchFirstColumn')
            ->with(
                'SELECT id FROM issue WHERE MATCH(title, culprit) AGAINST (? IN BOOLEAN MODE)',
                ['+fatal* +error*'],
            )
            ->willReturn([4, '7']);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $repo = new IssueSearchFilterTraitHarness($em, $this->createMock(EventTagRepository::class));

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())->method('andWhere')->with('i.id IN (:fulltextIssueIds)')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->with('fulltextIssueIds', [4, 7])->willReturnSelf();

        $repo->applyFullText($qb, 'fatal error');
    }

    public function testApplyTagUrlAndUserFiltersRespectEmptyValuesAndMatches(): void
    {
        $project = new Project();
        $tagRepository = $this->createMock(EventTagRepository::class);
        $repo = $this->repositoryWithPlatform(new SQLitePlatform(), $tagRepository);
        $qb = $this->createMock(QueryBuilder::class);

        $repo->applyTag($qb, new Project(), 'ops');
        $repo->applyUrl($qb, $project, ' ');
        $repo->applyUser($qb, null);

        $projectId = new \ReflectionProperty(Project::class, 'id');
        $projectId->setValue($project, 10);
        $tagCall = 0;
        $tagRepository->expects(self::exactly(2))
            ->method('findIssueIdsMatchingTag')
            ->willReturnCallback(function (Project $actualProject, string $tag) use ($project, &$tagCall): array {
                ++$tagCall;
                TestCase::assertSame($project, $actualProject);
                if (1 === $tagCall) {
                    TestCase::assertSame('ops', $tag);

                    return [9, 3];
                }

                TestCase::assertSame('none', $tag);

                return [];
            });

        $qb = $this->createMock(QueryBuilder::class);
        $andWhereCall = 0;
        $qb->expects(self::exactly(4))
            ->method('andWhere')
            ->willReturnCallback(function (string $expression) use (&$andWhereCall, $qb): QueryBuilder {
                ++$andWhereCall;
                $expected = [
                    'i.id IN (:tagFilterIssueIds)',
                    '1 = 0',
                    "EXISTS (SELECT 1 FROM App\\Issues\\Entity\\Event eurl WHERE eurl.issue = i AND eurl.project = :urlFilterProject AND eurl.requestUrl LIKE :urlLike ESCAPE '\\')",
                    "EXISTS (SELECT 1 FROM App\\Issues\\Entity\\Event euser WHERE euser.issue = i AND euser.userIdentifier LIKE :userLike ESCAPE '\\')",
                ];
                TestCase::assertSame($expected[$andWhereCall - 1], $expression);

                return $qb;
            });
        $setParameterCall = 0;
        $qb->expects(self::exactly(4))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$setParameterCall, $project, $qb): QueryBuilder {
                ++$setParameterCall;
                $expected = [
                    ['tagFilterIssueIds', [9, 3]],
                    ['urlFilterProject', $project],
                    ['urlLike', '%/orders/1%'],
                    ['userLike', '%alice\\_admin%'],
                ];
                TestCase::assertSame($expected[$setParameterCall - 1][0], $name);
                TestCase::assertSame($expected[$setParameterCall - 1][1], $value);

                return $qb;
            });

        $repo->applyTag($qb, $project, ' ops ');
        $repo->applyTag($qb, $project, 'none');
        $repo->applyUrl($qb, $project, ' /orders/1 ');
        $repo->applyUser($qb, ' alice_admin ');
    }

    private function repositoryWithPlatform(object $platform, ?EventTagRepository $tagRepository = null): IssueSearchFilterTraitHarness
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        return new IssueSearchFilterTraitHarness($em, $tagRepository ?? $this->createMock(EventTagRepository::class));
    }
}

final class IssueSearchFilterTraitHarness
{
    use IssueSearchFilterTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventTagRepository $eventTagRepository,
    ) {}

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    protected function eventTagRepository(): EventTagRepository
    {
        return $this->eventTagRepository;
    }

    public function applyFullText(QueryBuilder $qb, string $query): void
    {
        $this->applyFullTextOrLikeQuery($qb, $query);
    }

    public function applyTag(QueryBuilder $qb, Project $project, ?string $tag): void
    {
        $this->applyTagFilter($qb, $project, $tag);
    }

    public function applyUrl(QueryBuilder $qb, Project $project, ?string $url): void
    {
        $this->applyUrlFilter($qb, $project, $url);
    }

    public function applyUser(QueryBuilder $qb, ?string $user): void
    {
        $this->applyUserFilter($qb, $user);
    }
}
