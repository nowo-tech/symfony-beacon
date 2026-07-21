<?php

declare(strict_types=1);

namespace App\Notifications\Repository;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Entity\NotificationDigestBuffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationDigestBuffer>
 */
class NotificationDigestBufferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDigestBuffer::class);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function buffer(NotificationDestination $destination, array $payload): NotificationDigestBuffer
    {
        $row = new NotificationDigestBuffer();
        $row->setDestination($destination);
        $row->setPayload($payload);
        $this->getEntityManager()->persist($row);

        return $row;
    }

    /**
     * @return list<NotificationDigestBuffer>
     */
    public function findForDestination(NotificationDestination $destination): array
    {
        /** @var list<NotificationDigestBuffer> $result */
        $result = $this->createQueryBuilder('b')
            ->andWhere('b.destination = :destination')
            ->setParameter('destination', $destination)
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Destinations that currently have at least one buffered item.
     *
     * @return list<NotificationDestination>
     */
    public function findDestinationsWithBufferedItems(): array
    {
        /** @var list<NotificationDestination> $result */
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('d', 'p')
            ->from(NotificationDestination::class, 'd')
            ->innerJoin('d.project', 'p')
            ->innerJoin(NotificationDigestBuffer::class, 'b', 'WITH', 'b.destination = d')
            ->orderBy('d.id', 'ASC')
            ->distinct()
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @param list<NotificationDigestBuffer> $rows
     */
    public function removeAll(array $rows): void
    {
        $em = $this->getEntityManager();
        foreach ($rows as $row) {
            $em->remove($row);
        }
    }
}
