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

    /**
     * Destinations whose last recorded delivery failed (cross-project ops overview).
     *
     * @return list<NotificationDestination>
     */
    public function findWithFailedLastDelivery(?Project $project = null, int $limit = 25): array
    {
        $qb = $this->createQueryBuilder('d')
            ->innerJoin('d.project', 'p')->addSelect('p')
            ->andWhere('d.lastDeliverySuccess = false')
            ->andWhere('d.lastDeliveryAt IS NOT NULL')
            ->orderBy('d.lastDeliveryAt', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($project instanceof Project) {
            $qb->andWhere('d.project = :project')->setParameter('project', $project);
        }

        /** @var list<NotificationDestination> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Failed last deliveries limited to the given projects (dashboard Alerts panel).
     *
     * @param list<Project> $projects
     *
     * @return list<NotificationDestination>
     */
    public function findWithFailedLastDeliveryInProjects(
        array $projects,
        ?int $limit = 50,
        ?int $offset = null,
    ): array {
        if ([] === $projects) {
            return [];
        }

        $qb = $this->createQueryBuilder('d')
            ->innerJoin('d.project', 'p')->addSelect('p')
            ->andWhere('d.project IN (:projects)')
            ->andWhere('d.lastDeliverySuccess = false')
            ->andWhere('d.lastDeliveryAt IS NOT NULL')
            ->setParameter('projects', $projects)
            ->orderBy('d.lastDeliveryAt', 'DESC')
            ->addOrderBy('d.id', 'DESC');

        if (null !== $limit) {
            $qb->setMaxResults(max(1, $limit));
        }
        if (null !== $offset && $offset > 0) {
            $qb->setFirstResult($offset);
        }

        /** @var list<NotificationDestination> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @param list<Project> $projects
     */
    public function countWithFailedLastDeliveryInProjects(array $projects): int
    {
        if ([] === $projects) {
            return 0;
        }

        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.project IN (:projects)')
            ->andWhere('d.lastDeliverySuccess = false')
            ->andWhere('d.lastDeliveryAt IS NOT NULL')
            ->setParameter('projects', $projects)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count destinations whose last recorded delivery failed (instance-wide metrics).
     */
    public function countWithFailedLastDelivery(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.lastDeliverySuccess = false')
            ->andWhere('d.lastDeliveryAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
