<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\User;
use App\Shared\Doctrine\SqlLikeEscaper;
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
     * Batch lookup by email (config import). Keys are lowercase trimmed emails.
     *
     * @param list<string> $emails
     *
     * @return array<string, User>
     */
    public function findIndexedByEmails(array $emails): array
    {
        $normalized = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            if ('' !== $email) {
                $normalized[$email] = true;
            }
        }
        $list = array_keys($normalized);
        if ([] === $list) {
            return [];
        }

        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->andWhere('u.email IN (:emails)')
            ->setParameter('emails', $list)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($users as $user) {
            $map[strtolower(trim($user->getEmail()))] = $user;
        }

        return $map;
    }

    public function findOneBySlackUserId(string $slackUserId): ?User
    {
        $slackUserId = trim($slackUserId);
        if ('' === $slackUserId) {
            return null;
        }

        return $this->findOneBy(['slackUserId' => $slackUserId]);
    }

    /**
     * Admin directory with AuditKit blame users eager-loaded (avoids N+1).
     *
     * @return list<User>
     */
    public function findAllForAdminDirectory(?string $query = null, ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('u.updatedBy', 'ub')->addSelect('ub')
            // Eager-load instance roles so User::getRoles() does not N+1 the directory.
            ->leftJoin('u.instanceRoles', 'ir')->addSelect('ir')
            ->orderBy('u.email', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere("u.email LIKE :q ESCAPE '\\' OR u.displayName LIKE :q ESCAPE '\\'")
                ->setParameter('q', '%'.SqlLikeEscaper::escape(trim($query)).'%');
        }

        if (null !== $limit) {
            $qb->setMaxResults(max(1, $limit));
        }
        if (null !== $offset && $offset > 0) {
            $qb->setFirstResult($offset);
        }

        /** @var list<User> $users */
        $users = $qb->getQuery()->getResult();

        return $users;
    }

    public function countForAdminDirectory(?string $query = null): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)');

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere("u.email LIKE :q ESCAPE '\\' OR u.displayName LIKE :q ESCAPE '\\'")
                ->setParameter('q', '%'.SqlLikeEscaper::escape(trim($query)).'%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count accounts that store ROLE_ADMIN in the JSON roles column (no full-entity hydrate).
     *
     * Roles are persisted as JSON text; LIKE matches the encoded string on MySQL and SQLite.
     */
    public function countAdmins(bool $excludeAnonymized = false): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_ADMIN"%');

        if ($excludeAnonymized) {
            $qb->andWhere('u.anonymizedAt IS NULL');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->persist($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
