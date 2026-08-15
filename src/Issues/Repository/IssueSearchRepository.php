<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Issues\Entity\Issue;
use App\Issues\Repository\Query\IssueAssignmentQueryTrait;
use App\Issues\Repository\Query\IssueListQueryBuilderTrait;
use App\Issues\Repository\Query\IssueReleaseQueryTrait;
use App\Issues\Repository\Query\IssueSearchFilterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * List/filter/search queries for issues (dashboard, export, read API).
 *
 * Query-building internals live in {@see Query} traits mixed into this repository;
 * public method signatures are unchanged for callers.
 *
 * @extends ServiceEntityRepository<Issue>
 */
class IssueSearchRepository extends ServiceEntityRepository
{
    use IssueAssignmentQueryTrait;
    use IssueListQueryBuilderTrait;
    use IssueReleaseQueryTrait;
    use IssueSearchFilterTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly EventTagRepository $eventTagRepository,
    ) {
        parent::__construct($registry, Issue::class);
    }

    protected function eventTagRepository(): EventTagRepository
    {
        return $this->eventTagRepository;
    }
}
