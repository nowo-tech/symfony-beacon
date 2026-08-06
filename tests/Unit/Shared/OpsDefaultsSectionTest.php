<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Settings\OpsDefaultsSection;
use PHPUnit\Framework\TestCase;

final class OpsDefaultsSectionTest extends TestCase
{
    public function testKeysAndRouteRequirement(): void
    {
        self::assertSame('ops_defaults.section.governance', OpsDefaultsSection::Governance->tabLabelKey());
        self::assertSame('ops_defaults.section.ingest_help', OpsDefaultsSection::Ingest->helpKey());
        self::assertSame(
            'governance|ingest|metrics|inbound|notifications',
            OpsDefaultsSection::routeRequirement(),
        );
    }
}
