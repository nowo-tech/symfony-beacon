<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroupMembership;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
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

    /**
     * Users with access via direct membership or a linked group.
     *
     * @return list<User>
     */
    public function findUsersByProject(Project $project): array
    {
        /** @var list<User> $direct */
        $direct = $this->getEntityManager()->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->innerJoin('u.memberships', 'm')
            ->andWhere('m.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getResult();

        /** @var list<User> $viaGroup */
        $viaGroup = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT u')
            ->from(User::class, 'u')
            ->innerJoin(UserGroupMembership::class, 'gm', 'WITH', 'gm.user = u')
            ->innerJoin('gm.userGroup', 'g')
            ->innerJoin(ProjectGroupAccess::class, 'a', 'WITH', 'a.userGroup = g')
            ->andWhere('a.project = :project')
            ->setParameter('project', $project)
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
