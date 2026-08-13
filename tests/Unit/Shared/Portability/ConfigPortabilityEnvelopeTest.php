<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Portability;

use App\Shared\Portability\ConfigPortabilityEnvelope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigPortabilityEnvelopeTest extends TestCase
{
    public function testHeaderContainsSchemaVersionAndTimestamp(): void
    {
        $header = ConfigPortabilityEnvelope::header('beacon-test', 2);
        self::assertSame('beacon-test', $header['schema']);
        self::assertSame(2, $header['version']);
        self::assertNotFalse(strtotime($header['exported_at']));
    }

    public function testAssertSchemaAndVersions(): void
    {
        ConfigPortabilityEnvelope::assertSchema(['schema' => 'ok'], 'ok');
        ConfigPortabilityEnvelope::assertExactVersion(['version' => 3], 3);
        ConfigPortabilityEnvelope::assertExactVersion(['version' => '3'], 3);
        self::assertSame(2, ConfigPortabilityEnvelope::assertCompatibleVersion(['version' => 2], 3, 1));

        $this->expectException(InvalidArgumentException::class);
        ConfigPortabilityEnvelope::assertSchema(['schema' => 'other'], 'ok');
    }

    public function testAssertExactVersionRejectsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConfigPortabilityEnvelope::assertExactVersion([], 1);
    }

    public function testAssertCompatibleVersionRejectsOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConfigPortabilityEnvelope::assertCompatibleVersion(['version' => 9], 3, 1);
    }
}
