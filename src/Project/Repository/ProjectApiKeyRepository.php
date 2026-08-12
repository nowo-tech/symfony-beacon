<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Project\Entity\ProjectApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectApiKey>
 */
class ProjectApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectApiKey::class);
    }

    public function findActiveByPublicKey(string $publicKey): ?ProjectApiKey
    {
        /** @var ProjectApiKey|null $key */
        $key = $this->createQueryBuilder('k')
            ->leftJoin('k.project', 'p')->addSelect('p')
            ->andWhere('k.publicKey = :publicKey')
            ->andWhere('k.active = true')
            ->setParameter('publicKey', $publicKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $key;
    }
}
