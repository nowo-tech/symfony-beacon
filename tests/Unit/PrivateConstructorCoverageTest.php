<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Identity\Security\Permission;
use App\Ingest\IngestRouteRequirements;
use App\Issues\Service\IssueStatusTransition;
use App\Notifications\Formatter\OutboundPayloadFacts;
use App\Project\Security\ProjectPermission;
use App\Shared\Portability\ConfigPortabilityEnvelope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PrivateConstructorCoverageTest extends TestCase
{
    #[DataProvider('privateConstructorClasses')]
    public function testPrivateConstructorsCanBeInvokedViaReflection(string $class): void
    {
        $reflection = new ReflectionClass($class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        $constructor->invoke($instance);

        self::assertInstanceOf($class, $instance);
    }

    public static function privateConstructorClasses(): array
    {
        return [
            [Permission::class],
            [ProjectPermission::class],
            [IngestRouteRequirements::class],
            [IssueStatusTransition::class],
            [ConfigPortabilityEnvelope::class],
            [OutboundPayloadFacts::class],
        ];
    }
}
