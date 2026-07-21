<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueHistoryEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IssueHistoryEntry>
 */
final class IssueHistoryEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IssueHistoryEntry::class);
    }

    /**
     * @return list<IssueHistoryEntry>
     */
    public function findLatestForIssue(Issue $issue, int $limit = 50): array
    {
        /** @var list<IssueHistoryEntry> $rows */
        $rows = $this->createQueryBuilder('h')
            ->andWhere('h.issue = :issue')
            ->setParameter('issue', $issue)
            ->orderBy('h.createdAt', 'DESC')
            ->addOrderBy('h.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
