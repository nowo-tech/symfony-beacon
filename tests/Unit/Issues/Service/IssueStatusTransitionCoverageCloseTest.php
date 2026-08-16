<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Enum\IssueStatus;
use App\Issues\Service\IssueStatusTransition;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class IssueStatusTransitionCoverageCloseTest extends TestCase
{
    public function testPrivateConstructorCanBeInvokedViaReflection(): void
    {
        $ref = new ReflectionClass(IssueStatusTransition::class);
        $ctor = $ref->getConstructor();
        self::assertNotNull($ctor);
        $instance = $ref->newInstanceWithoutConstructor();
        $ctor->invoke($instance);
        self::assertInstanceOf(IssueStatusTransition::class, $instance);
    }
}
