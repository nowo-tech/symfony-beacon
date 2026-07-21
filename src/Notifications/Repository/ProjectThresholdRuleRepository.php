<?php

declare(strict_types=1);

namespace App\Notifications\Repository;

use App\Notifications\Entity\ProjectThresholdRule;
use App\Project\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectThresholdRule>
 */
final class ProjectThresholdRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectThresholdRule::class);
    }

    /**
     * @return list<ProjectThresholdRule>
     */
    public function findEnabledByProject(Project $project): array
    {
        /** @var list<ProjectThresholdRule> $result */
        $result = $this->createQueryBuilder('r')
            ->andWhere('r.project = :project')
            ->andWhere('r.enabled = true')
            ->setParameter('project', $project)
            ->orderBy('r.label', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
