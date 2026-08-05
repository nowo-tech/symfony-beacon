<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroupMembership;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;
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

    /**
     * Owner membership counts keyed by project id (one query; avoids N+1 in sole-owner checks).
     *
     * @param list<int> $projectIds
     *
     * @return array<int, int> project id => owner count
     */
    public function countOwnersByProjectIds(array $projectIds): array
    {
        if ([] === $projectIds) {
            return [];
        }

        /** @var list<array{projectId: int|string, cnt: int|string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.project) AS projectId, COUNT(m.id) AS cnt')
            ->andWhere('m.project IN (:projectIds)')
            ->andWhere('m.role = :role')
            ->setParameter('projectIds', $projectIds)
            ->setParameter('role', ProjectRole::Owner)
            ->groupBy('m.project')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($projectIds as $id) {
            $map[$id] = 0;
        }
        foreach ($rows as $row) {
            $map[(int) $row['projectId']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * Direct project memberships for a user (not via groups).
     *
     * @return list<ProjectMembership>
     */
    public function findByUser(User $user): array
    {
        /** @var list<ProjectMembership> $rows */
        $rows = $this->createQueryBuilder('m')
            ->innerJoin('m.project', 'p')
            ->addSelect('p')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Users with access via direct membership or a linked group.
     *
     * @return list<User>
     */
    public function findUsersByProject(Project $project): array
    {
        return $this->findUsersByProjects([$project]);
    }

    /**
     * Batch variant of {@see findUsersByProject()} (two queries total; avoids N+1 across projects).
     *
     * @param list<Project> $projects
     *
     * @return list<User>
     */
    public function findUsersByProjects(array $projects): array
    {
        $projectIds = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            if (null !== $id) {
                $projectIds[] = $id;
            }
        }
        if ([] === $projectIds) {
            return [];
        }

        /** @var list<User> $direct */
        $direct = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT u')
            ->from(User::class, 'u')
            ->innerJoin('u.memberships', 'm')
            ->andWhere('m.project IN (:projectIds)')
            ->setParameter('projectIds', $projectIds)
            ->getQuery()
            ->getResult();

        /** @var list<User> $viaGroup */
        $viaGroup = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT u')
            ->from(User::class, 'u')
            ->innerJoin(UserGroupMembership::class, 'gm', 'WITH', 'gm.user = u')
            ->innerJoin('gm.userGroup', 'g')
            ->innerJoin(ProjectGroupAccess::class, 'a', 'WITH', 'a.userGroup = g')
            ->andWhere('a.project IN (:projectIds)')
            ->setParameter('projectIds', $projectIds)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ([...$direct, ...$viaGroup] as $user) {
            $id = $user->getId();
            if (null !== $id) {
                $byId[$id] = $user;
            }
        }

        $users = array_values($byId);
        usort(
            $users,
            static function (User $a, User $b): int {
                $cmp = strcasecmp($a->getDisplayName(), $b->getDisplayName());

                return 0 !== $cmp ? $cmp : strcasecmp($a->getEmail(), $b->getEmail());
            },
        );

        return $users;
    }
}
