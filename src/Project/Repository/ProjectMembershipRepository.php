<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectMembership>
 */
class ProjectMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectMembership::class);
    }

    public function findOneByProjectAndUser(Project $project, User $user): ?ProjectMembership
    {
        return $this->findOneBy(['project' => $project, 'user' => $user]);
    }
}
