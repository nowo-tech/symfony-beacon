<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

/**
 * Queries for the admin activity timeline ({@see UserAction}).
 *
 * @extends ServiceEntityRepository<UserAction>
 */
class UserActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAction::class);
    }

    /**
     * Actions where the user is subject or actor, newest first.
     *
     * @return list<UserAction>
     */
    public function findForUser(User $user, int $limit = 100): array
    {
        /** @var list<UserAction> $rows */
        $rows = $this->createQueryBuilder('a')
            ->andWhere('a.subjectUser = :user OR a.actor = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Newest actions across the instance (admin users index “recent activity”).
     *
     * @return list<UserAction>
     */
    public function findLatest(int $limit = 50): array
    {
        /** @var list<UserAction> $rows */
        $rows = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Newest project-scoped audit entries using `context.project_uuid`.
     *
     * @param list<UserActionType> $allowedActions
     *
     * @return list<UserAction>
     */
    public function findForProject(
        Project $project,
        array $allowedActions,
        ?UserActionType $action = null,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        int $limit = 100,
    ): array {
        if ([] === $allowedActions) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform();
        $where = [$this->projectUuidPredicate($platform), 'action IN (:actions)'];
        $params = [
            'projectUuid' => $project->getUuid(),
            'actions' => array_map(
                static fn (UserActionType $allowed): string => $allowed->value,
                $allowedActions,
            ),
            'limit' => $limit,
        ];
        $types = [
            'projectUuid' => ParameterType::STRING,
            'actions' => ArrayParameterType::STRING,
            'limit' => ParameterType::INTEGER,
        ];

        if ($action instanceof UserActionType) {
            $where[] = 'action = :action';
            $params['action'] = $action->value;
            $types['action'] = ParameterType::STRING;
        }

        if ($from instanceof DateTimeImmutable) {
            $where[] = 'created_at >= :from';
            $params['from'] = $from->format('Y-m-d H:i:s');
            $types['from'] = ParameterType::STRING;
        }

        if ($to instanceof DateTimeImmutable) {
            $where[] = 'created_at <= :to';
            $params['to'] = $to->format('Y-m-d H:i:s');
            $types['to'] = ParameterType::STRING;
        }

        /** @var list<int|string> $rawIds */
        $rawIds = $connection->fetchFirstColumn(
            'SELECT id
                FROM user_action
               WHERE '.implode(' AND ', $where).'
            ORDER BY created_at DESC, id DESC
               LIMIT :limit',
            $params,
            $types,
        );

        if ([] === $rawIds) {
            return [];
        }

        $orderedIds = array_map(static fn (int|string $id): int => (int) $id, $rawIds);
        /** @var list<UserAction> $rows */
        $rows = $this->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $orderedIds)
            ->getQuery()
            ->getResult();

        $positions = array_flip($orderedIds);
        usort(
            $rows,
            static fn (UserAction $left, UserAction $right): int => ($positions[$left->getId() ?? 0] ?? \PHP_INT_MAX)
                <=> ($positions[$right->getId() ?? 0] ?? \PHP_INT_MAX),
        );

        return $rows;
    }

    private function projectUuidPredicate(object $platform): string
    {
        if ($platform instanceof MySQLPlatform) {
            return "JSON_UNQUOTE(JSON_EXTRACT(context, '$.project_uuid')) = :projectUuid";
        }

        if ($platform instanceof SQLitePlatform) {
            return "json_extract(context, '$.project_uuid') = :projectUuid";
        }

        throw new RuntimeException(\sprintf('Project audit query is unsupported on %s.', $platform::class));
    }
}
