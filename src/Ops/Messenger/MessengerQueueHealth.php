<?php

declare(strict_types=1);

namespace App\Ops\Messenger;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Throwable;

/**
 * Instance-wide Messenger queue depth for Ops overview, project Health UI, and /metrics.
 *
 * Prefers transport {@see MessageCountAwareInterface} counts (Redis streams
 * {@code async_ingest} / {@code async} / {@code failed}). Falls back to Doctrine
 * {@code messenger_messages} when the DSN is still doctrine:// or transports cannot count.
 *
 * @return-shape asyncPending(): array{pending: ?int, available: bool, failed: ?int, async_ingest: ?int, async: ?int}
 */
final readonly class MessengerQueueHealth
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'messenger.transport.async_ingest')]
        private ?object $asyncIngestTransport = null,
        #[Autowire(service: 'messenger.transport.async')]
        private ?object $asyncTransport = null,
        #[Autowire(service: 'messenger.transport.failed')]
        private ?object $failedTransport = null,
    ) {
    }

    /**
     * @return array{pending: ?int, available: bool, failed: ?int, async_ingest: ?int, async: ?int}
     */
    public function asyncPending(): array
    {
        try {
            $fromTransports = $this->countFromTransports();
            if (null !== $fromTransports) {
                return $fromTransports;
            }

            return $this->countFromDoctrineTable();
        } catch (Throwable) {
            return [
                'pending' => null,
                'available' => false,
                'failed' => null,
                'async_ingest' => null,
                'async' => null,
            ];
        }
    }

    /**
     * @return array{pending: ?int, available: bool, failed: ?int, async_ingest: ?int, async: ?int}|null
     */
    private function countFromTransports(): ?array
    {
        $ingest = $this->messageCount($this->asyncIngestTransport);
        $async = $this->messageCount($this->asyncTransport);
        if (null === $ingest && null === $async) {
            return null;
        }

        $failed = $this->messageCount($this->failedTransport);

        return [
            'pending' => ($ingest ?? 0) + ($async ?? 0),
            'available' => true,
            'failed' => $failed,
            'async_ingest' => $ingest,
            'async' => $async,
        ];
    }

    private function messageCount(?object $transport): ?int
    {
        if (!$transport instanceof MessageCountAwareInterface) {
            return null;
        }

        try {
            return max(0, $transport->getMessageCount());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{pending: ?int, available: bool, failed: ?int, async_ingest: ?int, async: ?int}
     */
    private function countFromDoctrineTable(): array
    {
        $connection = $this->entityManager->getConnection();
        $pending = $this->countDoctrineQueues($connection, ['async_ingest', 'async']);
        if (null === $pending) {
            return [
                'pending' => null,
                'available' => false,
                'failed' => null,
                'async_ingest' => null,
                'async' => null,
            ];
        }

        $failed = $this->countDoctrineQueues($connection, ['failed']);

        return [
            'pending' => $pending,
            'available' => true,
            'failed' => $failed,
            'async_ingest' => null,
            'async' => null,
        ];
    }

    /**
     * @param list<string> $queueNames
     */
    private function countDoctrineQueues(Connection $connection, array $queueNames): ?int
    {
        try {
            if (!$connection->createSchemaManager()->tablesExist(['messenger_messages'])) {
                return null;
            }

            $placeholders = implode(',', array_fill(0, \count($queueNames), '?'));
            $count = $connection->fetchOne(
                "SELECT COUNT(*) FROM messenger_messages WHERE queue_name IN ($placeholders) AND delivered_at IS NULL",
                $queueNames,
            );

            return false === $count ? null : (int) $count;
        } catch (Throwable) {
            return null;
        }
    }
}
