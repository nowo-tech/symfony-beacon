<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use DateTimeImmutable;

/**
 * One explicit Device Intelligence trust grant for Account → Security → Devices.
 */
final readonly class AccountTrustedDeviceRow
{
    public function __construct(
        public string $deviceId,
        public string $label,
        public DateTimeImmutable $trustedAt,
        public bool $current,
    ) {
    }
}
