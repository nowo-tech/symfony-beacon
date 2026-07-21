<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Identity\Entity\User;
use App\Issues\Entity\IssueSavedView;
use App\Project\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IssueSavedView>
 */
class IssueSavedViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IssueSavedView::class);
    }

    /**
     * @return list<IssueSavedView>
     */
    public function findForUserAndProject(User $user, Project $project): array
    {
        /** @var list<IssueSavedView> $result */
        $result = $this->createQueryBuilder('v')
            ->andWhere('v.user = :user')
            ->andWhere('v.project = :project')
            ->setParameter('user', $user)
            ->setParameter('project', $project)
            ->orderBy('v.name', 'ASC')
            ->addOrderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findOneForUserAndProject(string $uuid, User $user, Project $project): ?IssueSavedView
    {
        /** @var IssueSavedView|null $view */
        $view = $this->findOneBy([
            'uuid' => $uuid,
            'user' => $user,
            'project' => $project,
        ]);

        return $view;
    }
}
