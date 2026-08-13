<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\IngestRouteRequirements;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\Requirement\Requirement;

final class IngestRouteRequirementsTest extends TestCase
{
    public function testProjectRefAcceptsPositiveIntOrUuidPattern(): void
    {
        self::assertSame(
            Requirement::POSITIVE_INT.'|'.Requirement::UUID,
            IngestRouteRequirements::PROJECT_REF,
        );
        self::assertTrue((new ReflectionClass(IngestRouteRequirements::class))->isFinal());
    }
}
