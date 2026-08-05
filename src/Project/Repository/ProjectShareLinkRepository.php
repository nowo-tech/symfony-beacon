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

    public function findOneByUuid(string $uuid): ?ProjectShareLink
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    /**
     * Atomically claim one use (or unlimited bump) under revoke/expiry/max-uses constraints.
     *
     * @return bool true when this caller owns the use and may grant session access
     */
    public function tryClaimUse(ProjectShareLink $link, ?DateTimeImmutable $now = null): bool
    {
        $id = $link->getId();
        if (null === $id) {
            return false;
        }

        $now ??= new DateTimeImmutable();
        $conn = $this->getEntityManager()->getConnection();
        $affected = $conn->executeStatement(
            'UPDATE project_share_link
             SET use_count = use_count + 1, last_used_at = :now
             WHERE id = :id
               AND revoked_at IS NULL
               AND expires_at > :now
               AND (max_uses IS NULL OR use_count < max_uses)',
            [
                'id' => $id,
                'now' => $now->format('Y-m-d H:i:s'),
            ],
        );

        return $affected > 0;
    }

    /**
     * @return list<ProjectShareLink>
     */
    public function findActiveByProject(Project $project): array
    {
        /** @var list<ProjectShareLink> $rows */
        $rows = $this->createQueryBuilder('l')
            ->leftJoin('l.issue', 'i')->addSelect('i')
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
