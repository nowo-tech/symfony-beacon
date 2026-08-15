<?php

declare(strict_types=1);

namespace App\Shared\Sms;

/**
 * Outbound SMS payload (E.164 destination).
 */
final readonly class SmsOutboundMessage
{
    public function __construct(
        public string $toE164,
        public string $body,
        public ?string $fromE164 = null,
        public ?string $deviceId = null,
        public ?string $clientMessageId = null,
        public ?string $statusCallbackUrl = null,
    ) {
    }
}
