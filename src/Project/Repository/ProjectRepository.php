<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Projects the user can open via direct membership or a linked group.
     *
     * @return list<Project>
     */
    public function findAccessibleByUser(User $user, ?string $query = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->distinct()
            ->leftJoin('p.memberships', 'm')
            ->leftJoin('p.groupAccesses', 'ga')
            ->leftJoin('ga.userGroup', 'g')
            ->leftJoin('g.memberships', 'gm')
            ->andWhere('m.user = :user OR gm.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('p.name LIKE :q OR p.slug LIKE :q')
                ->setParameter('q', '%'.trim($query).'%');
        }

        /** @var list<Project> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function save(Project $project, bool $flush = true): void
    {
        $this->getEntityManager()->persist($project);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
