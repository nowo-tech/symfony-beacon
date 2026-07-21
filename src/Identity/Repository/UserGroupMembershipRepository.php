<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Persistence for users belonging to a {@see UserGroup}.
 *
 * @extends ServiceEntityRepository<UserGroupMembership>
 */
class UserGroupMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserGroupMembership::class);
    }

    /** Membership row for a user inside a group, if any. */
    public function findOneByGroupAndUser(UserGroup $group, User $user): ?UserGroupMembership
    {
        return $this->findOneBy(['userGroup' => $group, 'user' => $user]);
    }

    /**
     * Groups the user belongs to (hydrates group entity).
     *
     * @return list<UserGroupMembership>
     */
    public function findByUser(User $user): array
    {
        /** @var list<UserGroupMembership> $rows */
        $rows = $this->createQueryBuilder('m')
            ->innerJoin('m.userGroup', 'g')->addSelect('g')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @param list<int> $groupIds
     *
     * @return array<int, int> group id => member count
     */
    public function countByGroupIds(array $groupIds): array
    {
        $map = [];
        foreach ($groupIds as $id) {
            $map[$id] = 0;
        }
        if ([] === $groupIds) {
            return $map;
        }

        /** @var list<array{groupId: int|string, cnt: int|string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.userGroup) AS groupId, COUNT(m.id) AS cnt')
            ->andWhere('m.userGroup IN (:groups)')
            ->setParameter('groups', $groupIds)
            ->groupBy('m.userGroup')
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $map[(int) $row['groupId']] = (int) $row['cnt'];
        }

        return $map;
    }
}
