<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * All projects ordered by name (instance admin lists).
     *
     * @return list<Project>
     */
    public function findAllOrdered(?string $query = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('p.name LIKE :q OR p.slug LIKE :q')
                ->setParameter('q', '%'.trim($query).'%');
        }

        /** @var list<Project> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Projects the user can open via direct membership or a linked group.
     *
     * @return list<Project>
     */
    public function findAccessibleByUser(User $user, ?string $query = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->distinct()
            ->leftJoin('p.memberships', 'm')
            ->leftJoin('p.groupAccesses', 'ga')
            ->leftJoin('ga.userGroup', 'g')
            ->leftJoin('g.memberships', 'gm')
            ->andWhere('m.user = :user OR gm.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('p.name LIKE :q OR p.slug LIKE :q')
                ->setParameter('q', '%'.trim($query).'%');
        }

        /** @var list<Project> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Hydrate settings/admin associations without cartesian products across collections.
     */
    public function hydrateAccessGraph(Project $project): void
    {
        $this->createQueryBuilder('p')
            ->leftJoin('p.memberships', 'm')->addSelect('m')
            ->leftJoin('m.user', 'mu')->addSelect('mu')
            ->andWhere('p = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getResult();

        $this->createQueryBuilder('p')
            ->leftJoin('p.groupAccesses', 'ga')->addSelect('ga')
            ->leftJoin('ga.userGroup', 'g')->addSelect('g')
            ->andWhere('p = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getResult();

        $this->createQueryBuilder('p')
            ->leftJoin('p.notificationDestinations', 'd')->addSelect('d')
            ->andWhere('p = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getResult();
    }

    /**
     * Member and linked-group counts for admin project lists (avoids |length N+1).
     *
     * @param list<int> $projectIds
     *
     * @return array<int, array{members: int, groups: int}>
     */
    public function countAccessByProjectIds(array $projectIds): array
    {
        $map = [];
        foreach ($projectIds as $id) {
            $map[$id] = ['members' => 0, 'groups' => 0];
        }
        if ([] === $projectIds) {
            return $map;
        }

        $em = $this->getEntityManager();

        /** @var list<array{projectId: int|string, cnt: int|string}> $memberRows */
        $memberRows = $em->createQueryBuilder()
            ->select('IDENTITY(m.project) AS projectId, COUNT(m.id) AS cnt')
            ->from(ProjectMembership::class, 'm')
            ->andWhere('m.project IN (:projects)')
            ->setParameter('projects', $projectIds)
            ->groupBy('m.project')
            ->getQuery()
            ->getArrayResult();
        foreach ($memberRows as $row) {
            $map[(int) $row['projectId']]['members'] = (int) $row['cnt'];
        }

        /** @var list<array{projectId: int|string, cnt: int|string}> $groupRows */
        $groupRows = $em->createQueryBuilder()
            ->select('IDENTITY(ga.project) AS projectId, COUNT(ga.id) AS cnt')
            ->from(ProjectGroupAccess::class, 'ga')
            ->andWhere('ga.project IN (:projects)')
            ->setParameter('projects', $projectIds)
            ->groupBy('ga.project')
            ->getQuery()
            ->getArrayResult();
        foreach ($groupRows as $row) {
            $map[(int) $row['projectId']]['groups'] = (int) $row['cnt'];
        }

        return $map;
    }

    public function save(Project $project, bool $flush = true): void
    {
        $this->getEntityManager()->persist($project);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
