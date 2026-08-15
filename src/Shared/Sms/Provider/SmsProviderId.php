<?php

declare(strict_types=1);

namespace App\Shared\Sms\Provider;

/**
 * Configured SMS transport ids (env `SMS_PROVIDER`).
 */
enum SmsProviderId: string
{
    case Null = 'null';
    case SmsBridge = 'sms_bridge';

    public static function fromEnv(string $value): self
    {
        $normalized = strtolower(trim($value));
        if ('' === $normalized || 'none' === $normalized || 'disabled' === $normalized) {
            return self::Null;
        }

        return self::tryFrom($normalized) ?? self::Null;
    }
}
