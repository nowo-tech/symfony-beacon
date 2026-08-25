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

        $pattern = '#^(?:'.IngestRouteRequirements::PROJECT_REF.')$#';
        self::assertMatchesRegularExpression($pattern, '42');
        self::assertMatchesRegularExpression($pattern, '019fea2d-507b-7890-8b33-ca488db6f696');
        self::assertDoesNotMatchRegularExpression($pattern, '0');
        self::assertDoesNotMatchRegularExpression($pattern, 'not-a-project-ref');
    }
}
