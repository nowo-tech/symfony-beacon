<?php

declare(strict_types=1);

namespace App\Shared\Health;

/**
 * Readiness ping for the shared Redis (session, cache, Messenger).
 */
interface RedisProbe
{
    public function ping(): bool;
}
