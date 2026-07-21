<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Project\Entity\Project;
use App\Project\Entity\ProjectShareLink;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectShareLink>
 */
class ProjectShareLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectShareLink::class);
    }

    public function findOneByTokenHash(string $tokenHash): ?ProjectShareLink
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * @return list<ProjectShareLink>
     */
    public function findActiveByProject(Project $project): array
    {
        /** @var list<ProjectShareLink> $rows */
        $rows = $this->createQueryBuilder('l')
            ->andWhere('l.project = :project')
            ->andWhere('l.revokedAt IS NULL')
            ->andWhere('l.expiresAt > :now')
            ->setParameter('project', $project)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
