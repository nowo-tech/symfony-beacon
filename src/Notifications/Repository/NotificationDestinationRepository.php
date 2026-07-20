<?php

declare(strict_types=1);

namespace App\Notifications\Repository;

use App\Notifications\Entity\NotificationDestination;
use App\Project\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationDestination>
 */
class NotificationDestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDestination::class);
    }

    /**
     * @return list<NotificationDestination>
     */
    public function findEnabledByProject(Project $project): array
    {
        /** @var list<NotificationDestination> $result */
        $result = $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->andWhere('d.enabled = true')
            ->setParameter('project', $project)
            ->orderBy('d.label', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<NotificationDestination>
     */
    public function findByProject(Project $project): array
    {
        /** @var list<NotificationDestination> $result */
        $result = $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->setParameter('project', $project)
            ->orderBy('d.label', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
