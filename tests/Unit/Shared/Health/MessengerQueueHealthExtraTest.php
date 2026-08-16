<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Health;

use App\Shared\Health\MessengerQueueHealth;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessengerQueueHealthExtraTest extends TestCase
{
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
            ['pending' => null, 'available' => false],
            new MessengerQueueHealth($em)->asyncPending(),
        );
    }
}
