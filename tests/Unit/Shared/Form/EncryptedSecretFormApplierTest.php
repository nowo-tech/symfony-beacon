<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Form;

use App\Shared\Form\EncryptedSecretFormApplier;
use PHPUnit\Framework\TestCase;

final class EncryptedSecretFormApplierTest extends TestCase
{
    public function testClearSetsNullEvenWhenPlainProvided(): void
    {
        $value = 'keep';
        EncryptedSecretFormApplier::apply(true, 'new-secret', static function (?string $next) use (&$value): void {
            $value = $next;
        });

        self::assertNull($value);
    }

    public function testPlainOverrideUpdatesSetter(): void
    {
        $value = 'old';
        EncryptedSecretFormApplier::apply(false, 'new-secret', static function (?string $next) use (&$value): void {
            $value = $next;
        });

        self::assertSame('new-secret', $value);
    }

    public function testEmptyPlainLeavesValueUntouched(): void
    {
        $value = 'old';
        EncryptedSecretFormApplier::apply(false, '', static function (?string $next) use (&$value): void {
            $value = $next;
        });
        EncryptedSecretFormApplier::apply(false, null, static function (?string $next) use (&$value): void {
            $value = $next;
        });

        self::assertSame('old', $value);
    }
}
