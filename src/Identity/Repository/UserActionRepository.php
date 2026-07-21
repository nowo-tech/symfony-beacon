<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Queries for the admin activity timeline ({@see UserAction}).
 *
 * @extends ServiceEntityRepository<UserAction>
 */
class UserActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAction::class);
    }

    /**
     * Actions where the user is subject or actor, newest first.
     *
     * @return list<UserAction>
     */
    public function findForUser(User $user, int $limit = 100): array
    {
        /** @var list<UserAction> $rows */
        $rows = $this->createQueryBuilder('a')
            ->andWhere('a.subjectUser = :user OR a.actor = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Newest actions across the instance (admin users index “recent activity”).
     *
     * @return list<UserAction>
     */
    public function findLatest(int $limit = 50): array
    {
        /** @var list<UserAction> $rows */
        $rows = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
