<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\IngestRouteRequirements;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class IngestRouteRequirementsTest extends TestCase
{
    public function testProjectRefAcceptsPositiveIntOrUuidPattern(): void
    {
        self::assertTrue(new ReflectionClass(IngestRouteRequirements::class)->isFinal());
        self::assertMatchesRegularExpression('#^[0-9a-zA-Z_|\\[\\]\\-\\+\\*]+$#', IngestRouteRequirements::PROJECT_REF);
    }
}
