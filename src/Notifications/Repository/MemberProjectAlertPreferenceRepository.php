<?php

declare(strict_types=1);

namespace App\Notifications\Repository;

use App\Identity\Entity\User;
use App\Notifications\Entity\MemberProjectAlertPreference;
use App\Project\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberProjectAlertPreference>
 */
class MemberProjectAlertPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MemberProjectAlertPreference::class);
    }

    public function findOneByUserAndProject(User $user, Project $project): ?MemberProjectAlertPreference
    {
        return $this->findOneBy(['user' => $user, 'project' => $project]);
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<int, MemberProjectAlertPreference> keyed by project id
     */
    public function findIndexedByProjectIdForUser(User $user, array $projects): array
    {
        if ([] === $projects) {
            return [];
        }

        /** @var list<MemberProjectAlertPreference> $rows */
        $rows = $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.project IN (:projects)')
            ->setParameter('user', $user)
            ->setParameter('projects', $projects)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            $projectId = $row->getProject()?->getId();
            if (null !== $projectId) {
                $indexed[$projectId] = $row;
            }
        }

        return $indexed;
    }
}
