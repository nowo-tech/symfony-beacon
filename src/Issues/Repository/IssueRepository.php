<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Project\Entity\Project;
use App\Shared\Doctrine\SqlLikeEscaper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Issue entity lookups (fingerprint, uuid) and similarity helpers.
 *
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

    public function findOneByProjectAndUuid(Project $project, string $uuid): ?Issue
    {
        /** @var Issue|null $issue */
        $issue = $this->createQueryBuilder('i')
            ->leftJoin('i.assignee', 'assignee_user')->addSelect('assignee_user')
            ->leftJoin('i.duplicateOf', 'duplicate_of')->addSelect('duplicate_of')
            ->andWhere('i.project = :project')
            ->andWhere('i.uuid = :uuid')
            ->setParameter('project', $project)
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();

        return $issue;
    }

    public function findOneByUuidHydrated(string $uuid): ?Issue
    {
        /** @var Issue|null $issue */
        $issue = $this->createQueryBuilder('i')
            ->leftJoin('i.assignee', 'assignee_user')->addSelect('assignee_user')
            ->leftJoin('i.duplicateOf', 'duplicate_of')->addSelect('duplicate_of')
            ->leftJoin('i.project', 'p')->addSelect('p')
            ->andWhere('i.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();

        return $issue;
    }

    /** @return list<Issue> */
    public function findDuplicateCandidates(Project $project, Issue $exclude, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.id != :excludeId')
            ->setParameter('project', $project)
            ->setParameter('excludeId', $exclude->getId() ?? 0)
            ->orderBy('i.lastSeen', 'DESC')
            ->setMaxResults($limit);

        /** @var list<Issue> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /** @return list<Issue> */
    public function findSimilarIssues(Issue $issue, int $limit = 5): array
    {
        $project = $issue->getProject();
        if (!$project instanceof Project || $limit < 1) {
            return [];
        }

        $title = trim($issue->getTitle());
        if ('' === $title) {
            return [];
        }

        $tokens = preg_split('/\s+/u', $title) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            static fn (string $t): bool => mb_strlen($t) >= 3,
        ));
        if ([] === $tokens) {
            $tokens = [$title];
        }
        $tokens = \array_slice($tokens, 0, 4);

        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.id != :excludeId')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('project', $project)
            ->setParameter('excludeId', $issue->getId() ?? 0)
            ->setParameter('statuses', [IssueStatus::Unresolved, IssueStatus::Resolved])
            ->orderBy('i.lastSeen', 'DESC')
            ->setMaxResults($limit * 3);

        $ors = [];
        foreach ($tokens as $i => $token) {
            $param = 'sim'.$i;
            $ors[] = "LOWER(i.title) LIKE :{$param} ESCAPE '\\'";
            $qb->setParameter($param, '%'.SqlLikeEscaper::escape(mb_strtolower($token)).'%');
        }
        $qb->andWhere('('.implode(' OR ', $ors).')');

        /** @var list<Issue> $candidates */
        $candidates = $qb->getQuery()->getResult();

        $scored = [];
        $needle = mb_strtolower($title);
        foreach ($candidates as $candidate) {
            $other = mb_strtolower($candidate->getTitle());
            $score = 0;
            if ($other === $needle) {
                $score += 100;
            }
            foreach ($tokens as $token) {
                if (str_contains($other, mb_strtolower($token))) {
                    $score += 10;
                }
            }
            if ($score > 0) {
                $scored[] = ['issue' => $candidate, 'score' => $score, 'last' => $candidate->getLastSeen()->getTimestamp()];
            }
        }

        usort(
            $scored,
            static function (array $a, array $b): int {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                return $b['last'] <=> $a['last'];
            },
        );

        $out = [];
        foreach (\array_slice($scored, 0, $limit) as $row) {
            $out[] = $row['issue'];
        }

        return $out;
    }
}
