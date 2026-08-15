<?php

declare(strict_types=1);

namespace App\Shared\Sms;

/**
 * Result of an outbound SMS enqueue/send request to a provider.
 */
final readonly class SmsSendResult
{
    /**
     * @param array<string, mixed> $raw Provider response body (JSON-decoded) when available
     */
    public function __construct(
        public string $providerId,
        public string $providerMessageId,
        public string $status,
        public array $raw = [],
    ) {
    }
}
