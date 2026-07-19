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
     * @return list<Project>
     */
    public function findAccessibleByUser(User $user, ?string $query = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.memberships', 'm')
            ->andWhere('m.user = :user')
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
