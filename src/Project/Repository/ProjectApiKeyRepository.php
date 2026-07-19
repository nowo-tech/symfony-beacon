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
        return $this->findOneBy(['publicKey' => $publicKey, 'active' => true]);
    }
}
