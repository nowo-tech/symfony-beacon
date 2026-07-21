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
}
