<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\InstanceRole;
use App\Shared\Doctrine\SqlLikeEscaper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
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
    public function findAllOrdered(?string $query = null, ?int $limit = null, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('r.updatedBy', 'ub')->addSelect('ub')
            ->orderBy('r.name', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $like = '%'.SqlLikeEscaper::escape(trim($query)).'%';
            $qb->andWhere("r.name LIKE :q ESCAPE '\\' OR r.code LIKE :q ESCAPE '\\' OR r.description LIKE :q ESCAPE '\\'")
                ->setParameter('q', $like);
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit)->setFirstResult(max(0, $offset));
        }

        /** @var list<InstanceRole> $roles */
        $roles = $qb->getQuery()->getResult();

        return $roles;
    }

    public function countAllOrdered(?string $query = null): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)');

        if (null !== $query && '' !== trim($query)) {
            $like = '%'.SqlLikeEscaper::escape(trim($query)).'%';
            $qb->andWhere("r.name LIKE :q ESCAPE '\\' OR r.code LIKE :q ESCAPE '\\' OR r.description LIKE :q ESCAPE '\\'")
                ->setParameter('q', $like);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<int> $roleIds
     *
     * @return array<int, array{permissions: int, users: int}>
     */
    public function countByRoleIds(array $roleIds): array
    {
        $counts = [];
        foreach ($roleIds as $roleId) {
            $counts[$roleId] = ['permissions' => 0, 'users' => 0];
        }
        if ([] === $roleIds) {
            return $counts;
        }

        /** @var list<array{roleId: int|string, cnt: int|string}> $permissionRows */
        $permissionRows = $this->createQueryBuilder('r')
            ->select('r.id AS roleId, COUNT(DISTINCT p.id) AS cnt')
            ->leftJoin('r.permissions', 'p')
            ->andWhere('r.id IN (:roleIds)')
            ->setParameter('roleIds', $roleIds)
            ->groupBy('r.id')
            ->getQuery()
            ->getArrayResult();
        foreach ($permissionRows as $row) {
            $counts[(int) $row['roleId']]['permissions'] = (int) $row['cnt'];
        }

        /** @var list<array{roleId: int|string, cnt: int|string}> $userRows */
        $userRows = $this->createQueryBuilder('r')
            ->select('r.id AS roleId, COUNT(DISTINCT u.id) AS cnt')
            ->leftJoin('r.users', 'u')
            ->andWhere('r.id IN (:roleIds)')
            ->setParameter('roleIds', $roleIds)
            ->groupBy('r.id')
            ->getQuery()
            ->getArrayResult();
        foreach ($userRows as $row) {
            $counts[(int) $row['roleId']]['users'] = (int) $row['cnt'];
        }

        return $counts;
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
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }

    public function countAssignedUsers(InstanceRole $role): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(u.id)')
            ->innerJoin('r.users', 'u')
            ->andWhere('r = :role')
            ->setParameter('role', $role)
            ->getQuery()
            ->getSingleScalarResult();
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
