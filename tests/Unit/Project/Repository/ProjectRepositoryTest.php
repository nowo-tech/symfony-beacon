<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Repository;

use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class ProjectRepositoryTest extends TestCase
{
    public function testFindByUuidsReturnsEmptyForBlankInput(): void
    {
        $repo = $this->getMockBuilder(ProjectRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repo->expects(self::never())->method('createQueryBuilder');

        self::assertSame([], $repo->findByUuids([' ', '']));
    }

    public function testHydrateMembershipsReturnsEarlyWhenProjectsHaveNoIds(): void
    {
        $repo = $this->getMockBuilder(ProjectRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repo->expects(self::never())->method('createQueryBuilder');

        $repo->hydrateMembershipsForProjects([new Project()]);
        self::assertTrue(true);
    }

    public function testCountAccessByProjectIdsMapsGroupRowsAndBlankIngestPathReturnsNull(): void
    {
        $memberQuery = $this->createMock(Query::class);
        $memberQuery->method('getArrayResult')->willReturn([
            ['projectId' => 5, 'cnt' => 2],
        ]);

        $groupQuery = $this->createMock(Query::class);
        $groupQuery->method('getArrayResult')->willReturn([
            ['projectId' => 5, 'cnt' => 3],
        ]);

        $memberQb = $this->queryBuilderReturning($memberQuery);
        $groupQb = $this->queryBuilderReturning($groupQuery);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('createQueryBuilder')->willReturnOnConsecutiveCalls($memberQb, $groupQb);

        $repo = $this->getMockBuilder(ProjectRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['getEntityManager', 'find', 'findOneBy'])
            ->getMock();
        $repo->method('getEntityManager')->willReturn($em);
        $repo->expects(self::never())->method('find');
        $repo->expects(self::never())->method('findOneBy');

        self::assertSame([5 => ['members' => 2, 'groups' => 3]], $repo->countAccessByProjectIds([5]));
        self::assertNull($repo->findOneByIngestPath('   '));
    }

    private function queryBuilderReturning(Query $query): QueryBuilder
    {
        $qb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }
}
