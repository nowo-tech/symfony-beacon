<?php

declare(strict_types=1);

namespace App\Notifications\Repository;

use App\Identity\Entity\User;
use App\Notifications\Entity\MemberAccountAlertEvent;
use App\Notifications\Enum\MemberAlertEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberAccountAlertEvent>
 */
class MemberAccountAlertEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MemberAccountAlertEvent::class);
    }

    /**
     * @return array<string, MemberAccountAlertEvent> keyed by event value
     */
    public function findIndexedByEventForUser(User $user): array
    {
        /** @var list<MemberAccountAlertEvent> $rows */
        $rows = $this->findBy(['user' => $user]);
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->getEvent()->value] = $row;
        }

        return $indexed;
    }

    /**
     * @param list<int> $userIds
     *
     * @return array<int, array<string, MemberAccountAlertEvent>>
     */
    public function findIndexedByUserIds(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        /** @var list<MemberAccountAlertEvent> $rows */
        $rows = $this->createQueryBuilder('e')
            ->innerJoin('e.user', 'u')
            ->addSelect('u')
            ->andWhere('u.id IN (:userIds)')
            ->setParameter('userIds', array_values(array_unique($userIds)))
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            $userId = $row->getUser()?->getId();
            if (null === $userId) {
                continue;
            }

            $indexed[$userId][$row->getEvent()->value] = $row;
        }

        return $indexed;
    }

    public function findOneByUserAndEvent(User $user, MemberAlertEvent $event): ?MemberAccountAlertEvent
    {
        return $this->findOneBy(['user' => $user, 'event' => $event]);
    }

    public function deleteAllForUser(User $user): void
    {
        $this->createQueryBuilder('e')
            ->delete()
            ->andWhere('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
