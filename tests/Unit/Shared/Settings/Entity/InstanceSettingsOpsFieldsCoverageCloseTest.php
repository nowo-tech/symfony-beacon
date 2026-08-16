<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Entity;

use App\Shared\Settings\Entity\InstanceSettings;
use PHPUnit\Framework\TestCase;

final class InstanceSettingsOpsFieldsCoverageCloseTest extends TestCase
{
    public function testInboundFieldsAcceptExplicitNull(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setInboundMailDomain(null);
        $settings->setInboundWebhookSecret(null);

        self::assertNull($settings->getInboundMailDomain());
        self::assertNull($settings->getInboundWebhookSecret());
    }
}
