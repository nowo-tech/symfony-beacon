<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Entity;

use App\Shared\Settings\Entity\InstanceSettings;
use PHPUnit\Framework\TestCase;

final class InstanceSettingsOpsFieldsExtraTest extends TestCase
{
    public function testInboundMailDomainCanBeExplicitlyClearedToNull(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->setInboundMailDomain('inbound.example.test');

        self::assertSame($settings, $settings->setInboundMailDomain(null));
        self::assertNull($settings->getInboundMailDomain());
    }
}
