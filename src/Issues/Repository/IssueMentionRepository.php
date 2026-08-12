<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueMention;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IssueMention>
 */
class IssueMentionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IssueMention::class);
    }

    /**
     * @param list<Project> $projects
     *
     * @return list<IssueMention>
     */
    public function findInboxForUser(
        User $user,
        array $projects,
        bool $unreadOnly = false,
        ?int $limit = 50,
        ?int $offset = null,
    ): array {
        if ([] === $projects) {
            return [];
        }

        $qb = $this->createQueryBuilder('m')
            ->innerJoin('m.comment', 'c')->addSelect('c')
            ->innerJoin('c.author', 'author')->addSelect('author')
            ->innerJoin('c.issue', 'i')->addSelect('i')
            ->innerJoin('i.project', 'p')->addSelect('p')
            ->andWhere('m.mentionedUser = :user')
            ->andWhere('i.project IN (:projects)')
            ->setParameter('user', $user)
            ->setParameter('projects', $projects)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC');

        if ($unreadOnly) {
            $qb->andWhere('m.readAt IS NULL');
        }
        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }
        if (null !== $offset && $offset > 0) {
            $qb->setFirstResult($offset);
        }

        /** @var list<IssueMention> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    /**
     * @param list<Project> $projects
     */
    public function countInboxForUser(User $user, array $projects, bool $unreadOnly = false): int
    {
        if ([] === $projects) {
            return 0;
        }

        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->innerJoin('m.comment', 'c')
            ->innerJoin('c.issue', 'i')
            ->andWhere('m.mentionedUser = :user')
            ->andWhere('i.project IN (:projects)')
            ->setParameter('user', $user)
            ->setParameter('projects', $projects);

        if ($unreadOnly) {
            $qb->andWhere('m.readAt IS NULL');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findOneForUser(User $user, int $id): ?IssueMention
    {
        /** @var IssueMention|null $mention */
        $mention = $this->createQueryBuilder('m')
            ->andWhere('m.id = :id')
            ->andWhere('m.mentionedUser = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        return $mention;
    }

    public function isUserMentionedOnIssue(User $user, Issue $issue): bool
    {
        $count = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->innerJoin('m.comment', 'c')
            ->andWhere('m.mentionedUser = :user')
            ->andWhere('c.issue = :issue')
            ->setParameter('user', $user)
            ->setParameter('issue', $issue)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param list<Project> $projects
     */
    public function markAllReadForUser(User $user, array $projects): int
    {
        if ([] === $projects) {
            return 0;
        }

        $unread = $this->findInboxForUser($user, $projects, unreadOnly: true, limit: 500);
        $now = new DateTimeImmutable();
        foreach ($unread as $mention) {
            $mention->markRead($now);
        }

        return \count($unread);
    }
}
