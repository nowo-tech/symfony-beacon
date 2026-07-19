<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @return list<Event>
     */
    public function findLatestForIssue(Issue $issue, int $limit = 50): array
    {
        /** @var list<Event> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.issue = :issue')
            ->setParameter('issue', $issue)
            ->orderBy('e.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findOneByEventId(string $eventId): ?Event
    {
        return $this->findOneBy(['eventId' => $eventId]);
    }
}
