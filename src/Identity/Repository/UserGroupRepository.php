<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\UserGroup;
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
     * All groups ordered by display name.
     *
     * @return list<UserGroup>
     */
    public function findAllOrdered(): array
    {
        /** @var list<UserGroup> $groups */
        $groups = $this->createQueryBuilder('g')
            ->leftJoin('g.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('g.updatedBy', 'ub')->addSelect('ub')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $groups;
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
