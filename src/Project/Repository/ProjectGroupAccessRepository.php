<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Shared\ProjectRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Persistence for project↔group access links ({@see ProjectGroupAccess}).
 *
 * @extends ServiceEntityRepository<ProjectGroupAccess>
 */
class ProjectGroupAccessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectGroupAccess::class);
    }

    /** Existing link between a project and a user group, if any. */
    public function findOneByProjectAndGroup(Project $project, UserGroup $group): ?ProjectGroupAccess
    {
        return $this->findOneBy(['project' => $project, 'userGroup' => $group]);
    }

    /**
     * All project links for a user group, newest project name first.
     *
     * @return list<ProjectGroupAccess>
     */
    public function findByUserGroup(UserGroup $group): array
    {
        /** @var list<ProjectGroupAccess> $rows */
        $rows = $this->createQueryBuilder('a')
            ->innerJoin('a.project', 'p')
            ->addSelect('p')
            ->andWhere('a.userGroup = :group')
            ->setParameter('group', $group)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Highest project role granted to the user via any linked group, or null.
     *
     * Owners are never stored on group links; values are admin or member only.
     */
    public function findHighestGroupRoleForUser(Project $project, User $user): ?ProjectRole
    {
        /** @var list<ProjectGroupAccess> $rows */
        $rows = $this->createQueryBuilder('a')
            ->innerJoin('a.userGroup', 'g')
            ->innerJoin('g.memberships', 'gm')
            ->andWhere('a.project = :project')
            ->andWhere('gm.user = :user')
            ->setParameter('project', $project)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $best = null;
        $rank = [ProjectRole::Member->value => 1, ProjectRole::Admin->value => 2, ProjectRole::Owner->value => 3];
        foreach ($rows as $row) {
            $role = $row->getRole();
            if (null === $best || $rank[$role->value] > $rank[$best->value]) {
                $best = $role;
            }
        }

        return $best;
    }
}
