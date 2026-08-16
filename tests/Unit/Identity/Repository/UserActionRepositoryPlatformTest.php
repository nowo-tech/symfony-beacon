<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Repository;

use App\Identity\Repository\UserActionRepository;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class UserActionRepositoryPlatformTest extends TestCase
{
    public function testMySqlPredicatesAndUnsupportedPlatforms(): void
    {
        $repository = new UserActionRepository($this->createStub(ManagerRegistry::class));

        $context = new ReflectionMethod(UserActionRepository::class, 'contextUuidPredicate');
        self::assertStringContainsString("JSON_UNQUOTE(JSON_EXTRACT(context, '$.project_uuid'))", $context->invoke($repository, new MySQLPlatform(), 'project_uuid'));

        $contextIn = new ReflectionMethod(UserActionRepository::class, 'contextUuidInPredicate');
        self::assertStringContainsString("JSON_UNQUOTE(JSON_EXTRACT(context, '$.project_uuid')) IN (:projectUuids)", $contextIn->invoke($repository, new MySQLPlatform(), 'project_uuid'));

        try {
            $context->invoke($repository, new \stdClass(), 'project_uuid');
            self::fail('Expected unsupported platform exception for equality predicate');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('unsupported', $e->getMessage());
        }

        try {
            $contextIn->invoke($repository, new \stdClass(), 'project_uuid');
            self::fail('Expected unsupported platform exception for IN predicate');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('unsupported', $e->getMessage());
        }
    }
}
