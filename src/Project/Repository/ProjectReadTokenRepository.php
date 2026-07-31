<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Project\Entity\Project;
use App\Project\Entity\ProjectReadToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectReadToken>
 */
class ProjectReadTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectReadToken::class);
    }

    public function findActiveByTokenHash(string $tokenHash): ?ProjectReadToken
    {
        $token = $this->findOneBy(['tokenHash' => $tokenHash, 'active' => true]);
        if (!$token instanceof ProjectReadToken || !$token->isActive()) {
            return null;
        }

        return $token;
    }

    /**
     * @return list<ProjectReadToken>
     */
    public function findByProject(Project $project): array
    {
        /** @var list<ProjectReadToken> $rows */
        $rows = $this->createQueryBuilder('t')
            ->andWhere('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
