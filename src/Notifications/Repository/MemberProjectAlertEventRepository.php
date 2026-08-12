<?php

declare(strict_types=1);

namespace App\Notifications\Repository;

use App\Identity\Entity\User;
use App\Notifications\Entity\MemberProjectAlertEvent;
use App\Notifications\Enum\MemberAlertEvent;
use App\Project\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberProjectAlertEvent>
 */
class MemberProjectAlertEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MemberProjectAlertEvent::class);
    }

    /**
     * @return array<string, MemberProjectAlertEvent> keyed by event value
     */
    public function findIndexedByEventForUserAndProject(User $user, Project $project): array
    {
        /** @var list<MemberProjectAlertEvent> $rows */
        $rows = $this->findBy(['user' => $user, 'project' => $project]);
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->getEvent()->value] = $row;
        }

        return $indexed;
    }

    public function findOneByUserProjectAndEvent(
        User $user,
        Project $project,
        MemberAlertEvent $event,
    ): ?MemberProjectAlertEvent {
        return $this->findOneBy(['user' => $user, 'project' => $project, 'event' => $event]);
    }

    public function deleteAllForUserAndProject(User $user, Project $project): void
    {
        $this->createQueryBuilder('e')
            ->delete()
            ->andWhere('e.user = :user')
            ->andWhere('e.project = :project')
            ->setParameter('user', $user)
            ->setParameter('project', $project)
            ->getQuery()
            ->execute();
    }
}
