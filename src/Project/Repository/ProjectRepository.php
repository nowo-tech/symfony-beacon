<?php

declare(strict_types=1);

namespace App\Project\Repository;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Shared\Doctrine\SqlLikeEscaper;
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
    public function findAllOrdered(?string $query = null, ?int $limit = null, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.createdBy', 'cb')->addSelect('cb')
            ->leftJoin('p.updatedBy', 'ub')->addSelect('ub')
            ->orderBy('p.name', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $like = '%'.SqlLikeEscaper::escape(trim($query)).'%';
            $qb->andWhere("p.name LIKE :q ESCAPE '\\' OR p.slug LIKE :q ESCAPE '\\'")
                ->setParameter('q', $like);
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit)->setFirstResult(max(0, $offset));
        }

        /** @var list<Project> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function countAllOrdered(?string $query = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        if (null !== $query && '' !== trim($query)) {
            $like = '%'.SqlLikeEscaper::escape(trim($query)).'%';
            $qb->andWhere("p.name LIKE :q ESCAPE '\\' OR p.slug LIKE :q ESCAPE '\\'")
                ->setParameter('q', $like);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
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
            $qb->andWhere("p.name LIKE :q ESCAPE '\\' OR p.slug LIKE :q ESCAPE '\\'")
                ->setParameter('q', '%'.SqlLikeEscaper::escape(trim($query)).'%');
        }

        /** @var list<Project> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Load projects by public UUID in one query (admin config export selection).
     *
     * @param list<string> $uuids
     *
     * @return list<Project>
     */
    public function findByUuids(array $uuids): array
    {
        $normalized = [];
        foreach ($uuids as $uuid) {
            $uuid = trim($uuid);
            if ('' !== $uuid) {
                $normalized[$uuid] = true;
            }
        }
        $list = array_keys($normalized);
        if ([] === $list) {
            return [];
        }

        /** @var list<Project> $projects */
        $projects = $this->createQueryBuilder('p')
            ->andWhere('p.uuid IN (:uuids)')
            ->setParameter('uuids', $list)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $projects;
    }

    /**
     * Eager-load memberships + users for config export/import (avoids N+1).
     *
     * @param list<Project> $projects
     */
    public function hydrateMembershipsForProjects(array $projects): void
    {
        $ids = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }
        if ([] === $ids) {
            return;
        }

        $this->createQueryBuilder('p')
            ->leftJoin('p.memberships', 'm')->addSelect('m')
            ->leftJoin('m.user', 'mu')->addSelect('mu')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', array_values(array_unique($ids)))
            ->getQuery()
            ->getResult();
    }

    /**
     * Hydrate settings/admin associations without cartesian products across collections.
     */
    public function hydrateAccessGraph(Project $project): void
    {
        $this->hydrateMembershipsForProjects([$project]);

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

        $this->createQueryBuilder('p')
            ->leftJoin('p.apiKeys', 'ak')->addSelect('ak')
            ->andWhere('p = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getResult();

        $this->createQueryBuilder('p')
            ->leftJoin('p.thresholdRules', 'tr')->addSelect('tr')
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
