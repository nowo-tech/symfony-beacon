<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Repository;

use App\Identity\Entity\User;
use App\Notifications\Repository\PushSubscriptionRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class PushSubscriptionRepositoryTest extends TestCase
{
    public function testCountAllBuildsCountQuery(): void
    {
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('getSingleScalarResult')->willReturn('7');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())->method('select')->with('COUNT(s.id)')->willReturnSelf();
        $qb->expects(self::once())->method('getQuery')->willReturn($query);

        $repo = $this->repoWithQueryBuilder($qb);
        self::assertSame(7, $repo->countAll());
    }

    public function testFindForPushEnabledUsersReturnsEarlyForEmptyList(): void
    {
        $repo = $this->getMockBuilder(PushSubscriptionRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repo->expects(self::never())->method('createQueryBuilder');

        self::assertSame([], $repo->findForPushEnabledUsers([]));
    }

    public function testDeleteByEndpointHashForUserExecutesDeleteQuery(): void
    {
        $user = new User()->setEmail('push@example.com');
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('execute');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())->method('delete')->willReturnSelf();
        $qb->expects(self::exactly(2))->method('andWhere')->willReturnSelf();
        $qb->expects(self::exactly(2))->method('setParameter')->willReturnSelf();
        $qb->expects(self::once())->method('getQuery')->willReturn($query);

        $repo = $this->repoWithQueryBuilder($qb);
        $repo->deleteByEndpointHashForUser('hash-1', $user);
    }

    private function repoWithQueryBuilder(QueryBuilder $qb): PushSubscriptionRepository
    {
        $repo = $this->getMockBuilder(PushSubscriptionRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repo->expects(self::once())->method('createQueryBuilder')->with('s')->willReturn($qb);

        return $repo;
    }
}
