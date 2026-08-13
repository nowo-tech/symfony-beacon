<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Health;

use App\Shared\Health\MessengerQueueHealth;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessengerQueueHealthTest extends TestCase
{
    public function testReturnsPendingCountWhenTableExists(): void
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('tablesExist')->willReturn(true);
        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schema);
        $connection->method('fetchOne')->willReturn(7);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        self::assertSame(
            ['pending' => 7, 'available' => true],
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
            ['pending' => null, 'available' => false],
            new MessengerQueueHealth($em)->asyncPending(),
        );

        $emFail = $this->createStub(EntityManagerInterface::class);
        $emFail->method('getConnection')->willThrowException(new RuntimeException('down'));
        self::assertSame(
            ['pending' => null, 'available' => false],
            new MessengerQueueHealth($emFail)->asyncPending(),
        );
    }
}
