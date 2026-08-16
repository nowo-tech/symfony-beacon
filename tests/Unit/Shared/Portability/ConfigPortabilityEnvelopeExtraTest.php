<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Portability;

use App\Shared\Portability\ConfigPortabilityEnvelope;
use PHPUnit\Framework\TestCase;

final class ConfigPortabilityEnvelopeExtraTest extends TestCase
{
    public function testExactVersionMismatchThrowsUnsupportedVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported_version');
        ConfigPortabilityEnvelope::assertExactVersion(['version' => '2'], 1);
    }
}
