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
}
