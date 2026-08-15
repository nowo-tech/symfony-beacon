<?php

declare(strict_types=1);

namespace App\Issues\Repository\Query;

use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;

/**
 * Release-scoped issue queries (new-in-release, distinct releases, first-release counts).
 *
 * @phpstan-require-extends ServiceEntityRepository<Issue>
 */
trait IssueReleaseQueryTrait
{
    /**
     * @param list<Project> $projects
     *
     * @return list<Issue>
     */
    public function searchNewInRelease(
        array $projects,
        ?string $release = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        if ([] === $projects) {
            return [];
        }

        $qb = $this->createNewInReleaseQueryBuilder($projects, $release);
        $qb->leftJoin('i.project', 'nir_project')->addSelect('nir_project');
        $qb->orderBy('i.lastSeen', 'DESC')->addOrderBy('i.id', 'DESC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }
        if (null !== $offset && $offset > 0) {
            $qb->setFirstResult($offset);
        }

        /** @var list<Issue> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /** @param list<Project> $projects */
    public function countNewInRelease(array $projects, ?string $release = null): int
    {
        if ([] === $projects) {
            return 0;
        }

        $qb = $this->createNewInReleaseQueryBuilder($projects, $release);
        $qb->select('COUNT(i.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<Project> $projects
     *
     * @return list<string>
     */
    public function findDistinctFirstReleasesAcrossProjects(array $projects, int $limit = 40): array
    {
        if ([] === $projects) {
            return [];
        }

        $ids = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }
        if ([] === $ids) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT first_release AS release_value
            FROM issue
            WHERE project_id IN (:projectIds) AND first_release IS NOT NULL AND first_release <> ''
            GROUP BY first_release
            ORDER BY MAX(last_seen) DESC, first_release DESC
            LIMIT :limit
            SQL;

        /** @var list<int|string> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'projectIds' => $ids,
            'limit' => $limit,
        ], [
            'projectIds' => ArrayParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ]);

        $releases = [];
        foreach ($rows as $row) {
            $normalized = Issue::normalizeRelease((string) $row);
            if (null === $normalized) {
                continue;
            }
            $releases[] = $normalized;
        }

        return array_values(array_unique($releases));
    }

    /** @return list<string> */
    public function findDistinctReleases(Project $project): array
    {
        $projectId = $project->getId();
        if (null === $projectId) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT release_value
            FROM (
                SELECT first_release AS release_value, last_seen AS seen_at
                FROM issue
                WHERE project_id = :projectId AND first_release IS NOT NULL
                UNION ALL
                SELECT last_release AS release_value, last_seen AS seen_at
                FROM issue
                WHERE project_id = :projectId AND last_release IS NOT NULL
            ) releases
            WHERE release_value <> ''
            GROUP BY release_value
            ORDER BY MAX(seen_at) DESC, release_value DESC
            SQL;

        /** @var list<int|string> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'projectId' => $projectId,
        ], [
            'projectId' => ParameterType::INTEGER,
        ]);

        $releases = [];
        foreach ($rows as $row) {
            $normalized = Issue::normalizeRelease((string) $row);
            if (null === $normalized) {
                continue;
            }
            $releases[] = $normalized;
        }

        return array_values(array_unique($releases));
    }

    public function countNewIssuesByFirstRelease(Project $project, string $release): int
    {
        $normalized = Issue::normalizeRelease($release);
        if (null === $normalized) {
            return 0;
        }

        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.project = :project')
            ->andWhere('i.firstRelease = :release')
            ->setParameter('project', $project)
            ->setParameter('release', $normalized)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string, int> */
    public function countNewIssuesByFirstReleaseMap(Project $project): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.firstRelease AS release_value, COUNT(i.id) AS issue_count')
            ->andWhere('i.project = :project')
            ->andWhere('i.firstRelease IS NOT NULL')
            ->setParameter('project', $project)
            ->groupBy('i.firstRelease')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $normalized = Issue::normalizeRelease((string) ($row['release_value'] ?? ''));
            if (null === $normalized) {
                continue;
            }
            $counts[$normalized] = (int) ($row['issue_count'] ?? 0);
        }

        return $counts;
    }

    /** @return list<Issue> */
    public function findByRelease(Project $project, string $release, int $limit = 100): array
    {
        $normalized = Issue::normalizeRelease($release);
        if (null === $normalized) {
            return [];
        }

        /** @var list<Issue> $result */
        $result = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.firstRelease = :release OR i.lastRelease = :release')
            ->setParameter('project', $project)
            ->setParameter('release', $normalized)
            ->orderBy('i.lastSeen', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** @return list<Issue> */
    public function findLatestNewIssuesByFirstRelease(Project $project, string $release, int $limit = 8): array
    {
        $normalized = Issue::normalizeRelease($release);
        if (null === $normalized) {
            return [];
        }

        /** @var list<Issue> $result */
        $result = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.firstRelease = :release')
            ->setParameter('project', $project)
            ->setParameter('release', $normalized)
            ->orderBy('i.lastSeen', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** @param list<Project> $projects */
    private function createNewInReleaseQueryBuilder(array $projects, ?string $release): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project IN (:projects)')
            ->andWhere('i.firstRelease IS NOT NULL')
            ->setParameter('projects', $projects);

        $normalized = null !== $release && '' !== $release ? Issue::normalizeRelease($release) : null;
        if (null !== $normalized) {
            $qb->andWhere('i.firstRelease = :release')->setParameter('release', $normalized);
        }

        return $qb;
    }
}
