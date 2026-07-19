<?php

declare(strict_types=1);

namespace App\Analytics\Repository;

use App\Analytics\Entity\DailyProjectStat;
use App\Project\Entity\Project;
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

    public function findOrCreate(Project $project, \DateTimeImmutable $date): DailyProjectStat
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
        $from = (new \DateTimeImmutable('today'))->modify(\sprintf('-%d days', $days - 1));

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
}
