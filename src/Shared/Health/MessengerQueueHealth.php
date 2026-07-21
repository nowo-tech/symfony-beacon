<?php

declare(strict_types=1);

namespace App\Shared\Health;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Instance-wide Messenger async queue depth for health probes and project Health UI.
 */
final readonly class MessengerQueueHealth
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{pending: ?int, available: bool}
     */
    public function asyncPending(): array
    {
        try {
            $connection = $this->entityManager->getConnection();
            $pending = $this->countAsyncMessages($connection);

            return [
                'pending' => $pending,
                'available' => null !== $pending,
            ];
        } catch (Throwable) {
            return ['pending' => null, 'available' => false];
        }
    }

    private function countAsyncMessages(Connection $connection): ?int
    {
        try {
            if (!$connection->createSchemaManager()->tablesExist(['messenger_messages'])) {
                return null;
            }

            $count = $connection->fetchOne(
                "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'async' AND delivered_at IS NULL",
            );

            return false === $count ? null : (int) $count;
        } catch (Throwable) {
            return null;
        }
    }
}
