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

    /**
     * @return list<PerfTransaction>
     */
    public function findForProject(Project $project, bool $nPlusOneOnly = false, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('t.receivedAt', 'DESC')
            ->setMaxResults($limit);

        if ($nPlusOneOnly) {
            $qb->andWhere('t.nPlusOneCount > 0');
        }

        /** @var list<PerfTransaction> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
