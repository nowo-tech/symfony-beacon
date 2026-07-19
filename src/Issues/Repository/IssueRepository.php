<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Shared\IssueStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Issue>
 */
class IssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Issue::class);
    }

    public function findOneByProjectAndFingerprint(Project $project, string $fingerprint): ?Issue
    {
        return $this->findOneBy(['project' => $project, 'fingerprint' => $fingerprint]);
    }

    /**
     * @return list<Issue>
     */
    public function search(
        Project $project,
        ?string $query = null,
        ?string $level = null,
        ?IssueStatus $status = null,
        ?string $environment = null,
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->setParameter('project', $project)
            ->orderBy('i.lastSeen', 'DESC')
            ->setMaxResults(100);

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('i.title LIKE :q OR i.culprit LIKE :q')
                ->setParameter('q', '%'.trim($query).'%');
        }
        if (null !== $level && '' !== $level) {
            $qb->andWhere('i.level = :level')->setParameter('level', $level);
        }
        if ($status instanceof IssueStatus) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }
        if (null !== $environment && '' !== $environment) {
            $qb->innerJoin('i.events', 'e')
                ->andWhere('e.environment = :env')
                ->setParameter('env', $environment)
                ->distinct();
        }

        /** @var list<Issue> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
