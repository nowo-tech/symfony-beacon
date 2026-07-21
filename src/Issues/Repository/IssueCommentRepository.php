<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IssueComment>
 */
class IssueCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IssueComment::class);
    }

    /**
     * @return list<IssueComment>
     */
    public function findLatestForIssue(Issue $issue, int $limit = 100): array
    {
        /** @var list<IssueComment> $result */
        $result = $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'author')->addSelect('author')
            ->andWhere('c.issue = :issue')
            ->setParameter('issue', $issue)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
