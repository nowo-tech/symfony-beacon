<?php

declare(strict_types=1);

namespace App\Analytics\Repository;

use App\Analytics\Entity\DailyProjectStat;
use App\Project\Entity\Project;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyProjectStat>
 */
class DailyProjectStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyProjectStat::class);
    }

    public function findOrCreate(Project $project, DateTimeImmutable $date): DailyProjectStat
    {
        $day = $date->setTime(0, 0);
        $stat = $this->findOneBy(['project' => $project, 'statDate' => $day]);
        if (null !== $stat) {
            return $stat;
        }

        $stat = new DailyProjectStat();
        $stat->setProject($project);
        $stat->setStatDate($day);
        $this->getEntityManager()->persist($stat);

        return $stat;
    }

    /**
     * @return list<DailyProjectStat>
     */
    public function findLastDays(Project $project, int $days = 14): array
    {
        $from = $this->fromDateForLastDays($days);

        /** @var list<DailyProjectStat> $result */
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.project = :project')
            ->andWhere('s.statDate >= :from')
            ->setParameter('project', $project)
            ->setParameter('from', $from)
            ->orderBy('s.statDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Batch variant of {@see findLastDays()} (avoids N+1 on dashboard).
     *
     * @param list<Project> $projects
     *
     * @return array<int, list<DailyProjectStat>> keyed by project id
     */
    public function findLastDaysForProjects(array $projects, int $days = 14): array
    {
        $map = [];
        $ids = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            if (null === $id) {
                continue;
            }
            $ids[] = $id;
            $map[$id] = [];
        }
        if ([] === $ids) {
            return $map;
        }

        $from = $this->fromDateForLastDays($days);

        /** @var list<DailyProjectStat> $rows */
        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.project IN (:projects)')
            ->andWhere('s.statDate >= :from')
            ->setParameter('projects', $ids)
            ->setParameter('from', $from)
            ->orderBy('s.statDate', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($rows as $stat) {
            $projectId = $stat->getProject()?->getId();
            if (null === $projectId) {
                continue;
            }
            $map[$projectId][] = $stat;
        }

        return $map;
    }

    public function countLastDays(Project $project, int $days): int
    {
        $from = $this->fromDateForLastDays($days);

        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.project = :project')
            ->andWhere('s.statDate >= :from')
            ->setParameter('project', $project)
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Newest days first (page 1 = most recent).
     *
     * @return list<DailyProjectStat>
     */
    public function findLastDaysPage(Project $project, int $days, int $limit, int $offset): array
    {
        $from = $this->fromDateForLastDays($days);

        /** @var list<DailyProjectStat> $result */
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.project = :project')
            ->andWhere('s.statDate >= :from')
            ->setParameter('project', $project)
            ->setParameter('from', $from)
            ->orderBy('s.statDate', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Inclusive UTC date range, ascending by day.
     *
     * @return list<DailyProjectStat>
     */
    public function findInRange(Project $project, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $fromDay = $from->setTime(0, 0);
        $toDay = $to->setTime(0, 0);

        /** @var list<DailyProjectStat> $result */
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.project = :project')
            ->andWhere('s.statDate >= :from')
            ->andWhere('s.statDate <= :to')
            ->setParameter('project', $project)
            ->setParameter('from', $fromDay)
            ->setParameter('to', $toDay)
            ->orderBy('s.statDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    private function fromDateForLastDays(int $days): DateTimeImmutable
    {
        return new DateTimeImmutable('today', new DateTimeZone('UTC'))
            ->modify(\sprintf('-%d days', max(1, $days) - 1));
    }
}
