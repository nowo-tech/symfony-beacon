<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\InstanceRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InstanceRole>
 */
class InstanceRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstanceRole::class);
    }

    /**
     * @return list<InstanceRole>
     */
    public function findAllOrdered(?string $query = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')->addSelect('p')
            ->leftJoin('r.users', 'u')->addSelect('u')
            ->leftJoin('r.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('r.updatedBy', 'ub')->addSelect('ub')
            ->orderBy('r.name', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('r.name LIKE :q OR r.code LIKE :q OR r.description LIKE :q')
                ->setParameter('q', '%'.trim($query).'%');
        }

        /** @var list<InstanceRole> $roles */
        $roles = $qb->getQuery()->getResult();

        return $roles;
    }

    public function findOneByCode(string $code): ?InstanceRole
    {
        $normalized = strtoupper(trim($code));
        if (!str_starts_with($normalized, 'ROLE_')) {
            $normalized = 'ROLE_'.$normalized;
        }

        return $this->findOneBy(['code' => $normalized]);
    }

    /** Eager-load permissions + users for the role detail page. */
    public function hydrateDetail(InstanceRole $role): void
    {
        $this->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')->addSelect('p')
            ->leftJoin('r.users', 'u')->addSelect('u')
            ->leftJoin('r.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('r.updatedBy', 'ub')->addSelect('ub')
            ->andWhere('r = :role')
            ->setParameter('role', $role)
            ->getQuery()
            ->getResult();
    }

    /**
     * Permission keys granted by enabled roles assigned to the user.
     *
     * @return list<string>
     */
    public function findPermissionKeysForUserId(int $userId): array
    {
        /** @var list<string> $keys */
        $keys = $this->createQueryBuilder('r')
            ->select('DISTINCT p.key')
            ->innerJoin('r.users', 'u')
            ->innerJoin('r.permissions', 'p')
            ->andWhere('u.id = :userId')
            ->andWhere('r.enabled = true')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleColumnResult();

        return $keys;
    }
}
