<?php

declare(strict_types=1);

namespace App\Performance\Repository;

use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PerfTransaction>
 */
class PerfTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PerfTransaction::class);
    }

    public function countForProject(Project $project, bool $nPlusOneOnly = false): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.project = :project')
            ->setParameter('project', $project);

        if ($nPlusOneOnly) {
            $qb->andWhere('t.nPlusOneCount > 0');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<PerfTransaction>
     */
    public function findPageForProject(
        Project $project,
        bool $nPlusOneOnly,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('t.receivedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($nPlusOneOnly) {
            $qb->andWhere('t.nPlusOneCount > 0');
        }

        /** @var list<PerfTransaction> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
