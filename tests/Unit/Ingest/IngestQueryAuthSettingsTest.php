<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\Service\IngestQueryAuthSettings;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use PHPUnit\Framework\TestCase;

final class IngestQueryAuthSettingsTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testOverrideWinsOverOpsDefaults(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setIngestRejectQueryAuth(true);
        });

        $settings = new IngestQueryAuthSettings($ops, rejectQueryAuth: false);

        self::assertFalse($settings->shouldRejectQueryAuth());
    }

    public function testFallsBackToOpsDefaultsWhenOverrideNull(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setIngestRejectQueryAuth(true);
        });

        $settings = new IngestQueryAuthSettings($ops);

        self::assertTrue($settings->shouldRejectQueryAuth());
    }
}
