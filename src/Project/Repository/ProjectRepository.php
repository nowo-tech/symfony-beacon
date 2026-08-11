<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
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
            ->leftJoin('p.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('p.updatedBy', 'ub')->addSelect('ub')
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
            ->andWhere('(m.user = :user AND m.active = true) OR gm.user = :user')
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
     * Collect linked and assignable group ids for member-count lookups.
     *
     * @param iterable<UserGroup> $availableGroups
     *
     * @return list<int>
     */
    public static function collectGroupIds(Project $project, iterable $availableGroups): array
    {
        $groupIds = [];
        foreach ($project->getGroupAccesses() as $accessRow) {
            $groupId = $accessRow->getUserGroup()?->getId();
            if (null !== $groupId) {
                $groupIds[] = $groupId;
            }
        }
        foreach ($availableGroups as $group) {
            $groupId = $group->getId();
            if (null !== $groupId) {
                $groupIds[] = $groupId;
            }
        }

        return array_values(array_unique($groupIds));
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

    /**
     * Resolve an ingest path segment: public UUID (preferred) or legacy numeric primary key.
     */
    public function findOneByIngestPath(string $projectRef): ?Project
    {
        $projectRef = trim($projectRef);
        if ('' === $projectRef) {
            return null;
        }

        if (ctype_digit($projectRef)) {
            return $this->find((int) $projectRef);
        }

        return $this->findOneBy(['uuid' => $projectRef]);
    }

    public function save(Project $project, bool $flush = true): void
    {
        $this->getEntityManager()->persist($project);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
