<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\UserGroup;
use App\Shared\Doctrine\SqlLikeEscaper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Persistence for named user groups used in bulk project access.
 *
 * @extends ServiceEntityRepository<UserGroup>
 */
class UserGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserGroup::class);
    }

    /**
     * All groups ordered by display name (optional name/slug/description search).
     *
     * @return list<UserGroup>
     */
    public function findAllOrdered(?string $query = null, ?int $limit = null, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('g.updatedBy', 'ub')->addSelect('ub')
            ->orderBy('g.name', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $like = '%'.SqlLikeEscaper::escape(trim($query)).'%';
            $qb->andWhere("g.name LIKE :q ESCAPE '\\' OR g.slug LIKE :q ESCAPE '\\' OR g.description LIKE :q ESCAPE '\\'")
                ->setParameter('q', $like);
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit)->setFirstResult(max(0, $offset));
        }

        /** @var list<UserGroup> $groups */
        $groups = $qb->getQuery()->getResult();

        return $groups;
    }

    public function countAllOrdered(?string $query = null): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)');

        if (null !== $query && '' !== trim($query)) {
            $like = '%'.SqlLikeEscaper::escape(trim($query)).'%';
            $qb->andWhere("g.name LIKE :q ESCAPE '\\' OR g.slug LIKE :q ESCAPE '\\' OR g.description LIKE :q ESCAPE '\\'")
                ->setParameter('q', $like);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** Lookup by unique slug (normalized to lowercase). */
    public function findOneBySlug(string $slug): ?UserGroup
    {
        return $this->findOneBy(['slug' => strtolower(trim($slug))]);
    }

    /** Load memberships + users for a group detail page (avoids N+1). */
    public function hydrateMembers(UserGroup $group): void
    {
        $this->createQueryBuilder('g')
            ->leftJoin('g.memberships', 'm')->addSelect('m')
            ->leftJoin('m.user', 'u')->addSelect('u')
            ->leftJoin('g.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('g.updatedBy', 'ub')->addSelect('ub')
            ->andWhere('g = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getResult();
    }

    /** Eager-load AuditKit blame users for edit/detail (avoids N+1). */
    public function hydrateAudit(UserGroup $group): void
    {
        $this->createQueryBuilder('g')
            ->leftJoin('g.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('g.updatedBy', 'ub')->addSelect('ub')
            ->andWhere('g = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getResult();
    }
}
