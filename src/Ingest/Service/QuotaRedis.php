<?php

declare(strict_types=1);

namespace App\Ingest\Service;

/**
 * Atomic quota counter. The PSR-6 cache get/set path races under two ingest workers.
 */
interface QuotaRedis
{
    /**
     * @param array<int|string, mixed> $options
     */
    public function set(string $key, string $value, array $options): mixed;

    public function get(string $key): mixed;

    public function incr(string $key): int|false;

    public function expireAt(string $key, int $timestamp): bool;
}
