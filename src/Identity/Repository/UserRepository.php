<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => strtolower(trim($email))]);
    }

    /**
     * Admin directory with AuditKit blame users eager-loaded (avoids N+1).
     *
     * @return list<User>
     */
    public function findAllForAdminDirectory(): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->leftJoin('u.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('u.updatedBy', 'ub')->addSelect('ub')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        return $users;
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->persist($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
