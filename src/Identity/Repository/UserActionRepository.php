<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\Entity\UserGroup;
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
     * @param list<UserActionType> $allowedActions empty = no action-type restriction (legacy callers)
     *
     * @return list<UserAction>
     */
    public function findForUser(
        User $user,
        array $allowedActions = [],
        ?UserActionType $action = null,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        int $limit = 100,
    ): array {
        if ($action instanceof UserActionType && [] !== $allowedActions && !\in_array($action, $allowedActions, true)) {
            return [];
        }

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.actor', 'actor')->addSelect('actor')
            ->leftJoin('a.subjectUser', 'subjectUser')->addSelect('subjectUser')
            ->andWhere('a.subjectUser = :user OR a.actor = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit);

        if ([] !== $allowedActions) {
            $qb->andWhere('a.action IN (:allowed)')
                ->setParameter('allowed', $allowedActions);
        }

        if ($action instanceof UserActionType) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }

        if ($from instanceof DateTimeImmutable) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', $from);
        }

        if ($to instanceof DateTimeImmutable) {
            $qb->andWhere('a.createdAt <= :to')->setParameter('to', $to);
        }

        /** @var list<UserAction> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    /**
     * Actor-scoped product actions for the dashboard Recent activity panel.
     *
     * When {@code $projectUuids} is non-empty, only rows whose {@code context.project_uuid}
     * is in that set are returned (SQL JSON filter).
     *
     * @param list<UserActionType> $allowedActions
     * @param list<string>         $projectUuids
     *
     * @return list<UserAction>
     */
    public function findActorProductActivity(
        User $actor,
        array $allowedActions,
        array $projectUuids = [],
        int $limit = 50,
        int $offset = 0,
    ): array {
        $actorId = $actor->getId();
        if ([] === $allowedActions || null === $actorId || [] === $projectUuids) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform();
        $params = [
            'actorId' => $actorId,
            'actions' => array_map(
                static fn (UserActionType $allowed): string => $allowed->value,
                $allowedActions,
            ),
            'projectUuids' => $projectUuids,
            'limit' => max(1, $limit),
            'offset' => max(0, $offset),
        ];
        $types = [
            'actorId' => ParameterType::INTEGER,
            'actions' => ArrayParameterType::STRING,
            'projectUuids' => ArrayParameterType::STRING,
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ];

        /** @var list<int|string> $rawIds */
        $rawIds = $connection->fetchFirstColumn(
            'SELECT id
                FROM user_action
               WHERE actor_id = :actorId
                 AND action IN (:actions)
                 AND '.$this->contextUuidInPredicate($platform, 'project_uuid').'
            ORDER BY created_at DESC, id DESC
               LIMIT :limit OFFSET :offset',
            $params,
            $types,
        );

        return $this->hydrateOrderedByIds($rawIds);
    }

    /**
     * @param list<UserActionType> $allowedActions
     * @param list<string>         $projectUuids
     */
    public function countActorProductActivity(
        User $actor,
        array $allowedActions,
        array $projectUuids = [],
    ): int {
        $actorId = $actor->getId();
        if ([] === $allowedActions || null === $actorId || [] === $projectUuids) {
            return 0;
        }

        $connection = $this->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform();

        return (int) $connection->fetchOne(
            'SELECT COUNT(id)
                FROM user_action
               WHERE actor_id = :actorId
                 AND action IN (:actions)
                 AND '.$this->contextUuidInPredicate($platform, 'project_uuid'),
            [
                'actorId' => $actorId,
                'actions' => array_map(
                    static fn (UserActionType $allowed): string => $allowed->value,
                    $allowedActions,
                ),
                'projectUuids' => $projectUuids,
            ],
            [
                'actorId' => ParameterType::INTEGER,
                'actions' => ArrayParameterType::STRING,
                'projectUuids' => ArrayParameterType::STRING,
            ],
        );
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
            ->leftJoin('a.actor', 'actor')->addSelect('actor')
            ->leftJoin('a.subjectUser', 'subjectUser')->addSelect('subjectUser')
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
        return $this->findForContextUuid(
            'project_uuid',
            $project->getUuid(),
            $allowedActions,
            $action,
            $from,
            $to,
            $limit,
        );
    }

    /**
     * Newest group-scoped audit entries using `context.group_uuid`.
     *
     * @param list<UserActionType> $allowedActions
     *
     * @return list<UserAction>
     */
    public function findForGroup(
        UserGroup $group,
        array $allowedActions,
        ?UserActionType $action = null,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        int $limit = 100,
    ): array {
        return $this->findForContextUuid(
            'group_uuid',
            $group->getUuid(),
            $allowedActions,
            $action,
            $from,
            $to,
            $limit,
        );
    }

    /**
     * @param list<UserActionType> $allowedActions
     *
     * @return list<UserAction>
     */
    private function findForContextUuid(
        string $contextKey,
        string $uuid,
        array $allowedActions,
        ?UserActionType $action,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        int $limit,
    ): array {
        if ([] === $allowedActions) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform();
        $where = [$this->contextUuidPredicate($platform, $contextKey), 'action IN (:actions)'];
        $params = [
            'contextUuid' => $uuid,
            'actions' => array_map(
                static fn (UserActionType $allowed): string => $allowed->value,
                $allowedActions,
            ),
            'limit' => $limit,
        ];
        $types = [
            'contextUuid' => ParameterType::STRING,
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

        return $this->hydrateOrderedByIds($rawIds);
    }

    /**
     * @param list<int|string> $rawIds
     *
     * @return list<UserAction>
     */
    private function hydrateOrderedByIds(array $rawIds): array
    {
        if ([] === $rawIds) {
            return [];
        }

        $orderedIds = array_map(static fn (int|string $id): int => (int) $id, $rawIds);
        /** @var list<UserAction> $rows */
        $rows = $this->createQueryBuilder('a')
            ->leftJoin('a.actor', 'actor')->addSelect('actor')
            ->leftJoin('a.subjectUser', 'subjectUser')->addSelect('subjectUser')
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

    private function contextUuidPredicate(object $platform, string $contextKey): string
    {
        $path = '$.'.$contextKey;
        if ($platform instanceof MySQLPlatform) {
            return "JSON_UNQUOTE(JSON_EXTRACT(context, '".$path."')) = :contextUuid";
        }

        if ($platform instanceof SQLitePlatform) {
            return "json_extract(context, '".$path."') = :contextUuid";
        }

        throw new RuntimeException(\sprintf('Identity audit query is unsupported on %s.', $platform::class));
    }

    private function contextUuidInPredicate(object $platform, string $contextKey): string
    {
        $path = '$.'.$contextKey;
        if ($platform instanceof MySQLPlatform) {
            return "JSON_UNQUOTE(JSON_EXTRACT(context, '".$path."')) IN (:projectUuids)";
        }

        if ($platform instanceof SQLitePlatform) {
            return "json_extract(context, '".$path."') IN (:projectUuids)";
        }

        throw new RuntimeException(\sprintf('Identity audit query is unsupported on %s.', $platform::class));
    }
}
