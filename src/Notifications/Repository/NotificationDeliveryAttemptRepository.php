<?php

declare(strict_types=1);

namespace App\Notifications\Repository;

use App\Notifications\Entity\NotificationDeliveryAttempt;
use App\Notifications\Entity\NotificationDestination;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationDeliveryAttempt>
 */
final class NotificationDeliveryAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDeliveryAttempt::class);
    }

    public function record(
        NotificationDestination $destination,
        bool $successful,
        ?string $errorSnippet = null,
        ?DateTimeImmutable $attemptedAt = null,
    ): NotificationDeliveryAttempt {
        $attempt = new NotificationDeliveryAttempt();
        $attempt->setAttemptedAt($attemptedAt ?? new DateTimeImmutable());
        $attempt->setSuccessful($successful);
        $attempt->setErrorSnippet($errorSnippet);
        $destination->addDeliveryAttempt($attempt);

        $this->getEntityManager()->persist($attempt);

        return $attempt;
    }

    /**
     * @return list<NotificationDeliveryAttempt>
     */
    public function findRecentForDestination(NotificationDestination $destination, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.destination = :destination')
            ->setParameter('destination', $destination)
            ->orderBy('a.attemptedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        /** @var list<NotificationDeliveryAttempt> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Recent attempts for many destinations (avoids N+1 on project settings).
     *
     * @param list<NotificationDestination> $destinations
     *
     * @return array<int, list<NotificationDeliveryAttempt>> keyed by destination id
     */
    public function findRecentByDestinations(array $destinations, int $limitPerDestination = 25): array
    {
        $map = [];
        $ids = [];
        foreach ($destinations as $destination) {
            $id = $destination->getId();
            if (null === $id) {
                continue;
            }
            $ids[] = $id;
            $map[$id] = [];
        }
        if ([] === $ids) {
            return $map;
        }

        /** @var list<NotificationDeliveryAttempt> $rows */
        $rows = $this->createQueryBuilder('a')
            ->andWhere('a.destination IN (:destinations)')
            ->setParameter('destinations', $ids)
            ->orderBy('a.attemptedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($rows as $attempt) {
            $destinationId = $attempt->getDestination()?->getId();
            if (null === $destinationId) {
                continue;
            }
            if (\count($map[$destinationId]) >= $limitPerDestination) {
                continue;
            }
            $map[$destinationId][] = $attempt;
        }

        return $map;
    }

    /**
     * @param list<NotificationDeliveryAttempt> $attempts
     */
    public function removeAll(array $attempts): void
    {
        $em = $this->getEntityManager();
        foreach ($attempts as $attempt) {
            $em->remove($attempt);
        }
    }
}
