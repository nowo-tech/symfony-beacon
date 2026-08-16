<?php

declare(strict_types=1);

namespace App\Notifications\Repository;

use App\Identity\Entity\User;
use App\Notifications\Entity\PushSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    public function findOneByEndpointHash(string $endpointHash): ?PushSubscription
    {
        return $this->findOneBy(['endpointHash' => $endpointHash]);
    }

    /**
     * Total stored browser push endpoints (instance-wide dogfood / ops checks).
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<PushSubscription>
     */
    public function findByUser(User $user): array
    {
        /** @var list<PushSubscription> $rows */
        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Active push subscriptions for users with push enabled on a project.
     *
     * @param list<User> $users
     *
     * @return list<PushSubscription>
     */
    public function findForPushEnabledUsers(array $users): array
    {
        if ([] === $users) {
            return [];
        }

        /** @var list<PushSubscription> $rows */
        $rows = $this->createQueryBuilder('s')
            ->innerJoin('s.user', 'u')
            ->addSelect('u')
            ->andWhere('s.user IN (:users)')
            ->andWhere('u.uiPreferences.pushNotificationsEnabled = true')
            ->setParameter('users', $users)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Deletes a push subscription only when it belongs to the given user (anti-IDOR).
     */
    public function deleteByEndpointHashForUser(string $endpointHash, User $user): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->andWhere('s.endpointHash = :hash')
            ->andWhere('s.user = :user')
            ->setParameter('hash', $endpointHash)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
