<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops\Messenger;

use App\Ops\Messenger\MessengerQueueHealth;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

final class MessengerQueueHealthTest extends TestCase
{
    public function testPrefersMessageCountAwareTransportsOverDoctrine(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(new RuntimeException('should not use doctrine'));

        $health = new MessengerQueueHealth(
            $em,
            $this->countingTransport(3),
            $this->countingTransport(4),
            $this->countingTransport(2),
        );

        self::assertSame(
            [
                'pending' => 7,
                'available' => true,
                'failed' => 2,
                'async_ingest' => 3,
                'async' => 4,
            ],
            $health->asyncPending(),
        );
    }

    public function testFallsBackToDoctrineWhenTransportsCannotCount(): void
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('tablesExist')->willReturn(true);
        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schema);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(7, 1);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        self::assertSame(
            [
                'pending' => 7,
                'available' => true,
                'failed' => 1,
                'async_ingest' => null,
                'async' => null,
            ],
            new MessengerQueueHealth($em)->asyncPending(),
        );
    }

    public function testUnavailableWhenTableMissingOrQueryFails(): void
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('tablesExist')->willReturn(false);
        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schema);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        self::assertSame(
            [
                'pending' => null,
                'available' => false,
                'failed' => null,
                'async_ingest' => null,
                'async' => null,
            ],
            new MessengerQueueHealth($em)->asyncPending(),
        );

        $emFail = $this->createStub(EntityManagerInterface::class);
        $emFail->method('getConnection')->willThrowException(new RuntimeException('down'));
        self::assertSame(
            [
                'pending' => null,
                'available' => false,
                'failed' => null,
                'async_ingest' => null,
                'async' => null,
            ],
            new MessengerQueueHealth($emFail)->asyncPending(),
        );
    }

    public function testMessageCountAwareTransportExceptionsAreIgnored(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(new RuntimeException('should not use doctrine'));

        $throwing = new readonly class implements MessageCountAwareInterface {
            public function getMessageCount(): int
            {
                throw new RuntimeException('redis down');
            }
        };

        self::assertSame(
            [
                'pending' => 4,
                'available' => true,
                'failed' => null,
                'async_ingest' => null,
                'async' => 4,
            ],
            new MessengerQueueHealth(
                $em,
                $throwing,
                $this->countingTransport(4),
                $throwing,
            )->asyncPending(),
        );
    }

    public function testAsyncPendingHandlesSchemaInspectionFailures(): void
    {
        $schema = $this->createMock(AbstractSchemaManager::class);
        $schema->expects(self::once())
            ->method('tablesExist')
            ->willThrowException(new RuntimeException('boom'));

        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schema);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        self::assertSame(
            [
                'pending' => null,
                'available' => false,
                'failed' => null,
                'async_ingest' => null,
                'async' => null,
            ],
            new MessengerQueueHealth($em)->asyncPending(),
        );
    }

    private function countingTransport(int $count): object
    {
        return new readonly class($count) implements MessageCountAwareInterface {
            public function __construct(private int $count)
            {
            }

            public function getMessageCount(): int
            {
                return $this->count;
            }
        };
    }
}
