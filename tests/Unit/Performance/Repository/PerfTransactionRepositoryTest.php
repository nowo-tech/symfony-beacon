<?php

declare(strict_types=1);

namespace App\Tests\Unit\Performance\Repository;

use App\Performance\Entity\PerfTransaction;
use App\Performance\Repository\PerfTransactionRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class PerfTransactionRepositoryTest extends TestCase
{
    public function testHydrateSpansBuildsExpectedQuery(): void
    {
        $transaction = new PerfTransaction();
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())->method('leftJoin')->with('t.spans', 's')->willReturnSelf();
        $qb->expects(self::once())->method('addSelect')->with('s')->willReturnSelf();
        $qb->expects(self::once())->method('andWhere')->with('t = :transaction')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->with('transaction', $transaction)->willReturnSelf();
        $qb->expects(self::once())->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(PerfTransactionRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repo->expects(self::once())->method('createQueryBuilder')->with('t')->willReturn($qb);

        $repo->hydrateSpans($transaction);
    }
}
