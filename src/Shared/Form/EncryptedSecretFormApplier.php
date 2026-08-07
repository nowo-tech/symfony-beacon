<?php

declare(strict_types=1);

namespace App\Shared\Form;

/**
 * Applies optional encrypted-secret form fields (clear checkbox + plain override).
 */
final class EncryptedSecretFormApplier
{
    public static function apply(bool $clear, ?string $plain, callable $setter): void
    {
        if ($clear) {
            $setter(null);

            return;
        }

        if (null !== $plain && '' !== $plain) {
            $setter($plain);
        }
    }
}
