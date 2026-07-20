<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Application kernel.
 *
 * FrankenPHP HTTP runtime notes (see docs/FRANKENPHP-CODING.md):
 *
 * - MODE=classic: normal per-request lifecycle; RESET_KERNEL is irrelevant for isolation.
 * - MODE=worker + RESET_KERNEL=false: Kernel reused; services must implement ResetInterface
 *   when they hold request state (tagged kernel.reset). Avoid statics and $_ENV mutations.
 * - MODE=worker + RESET_KERNEL=true: Kernel is cloned after each request (APP_RUNTIME_MODE
 *   worker=2). Override __clone() if this class keeps non-resettable fields; process-level
 *   statics/globals still leak across requests.
 *
 * Project rule: write code safe for worker + RESET_KERNEL=false.
 *
 * @see docs/FRANKENPHP-CODING.md
 * @see specs/001-bootstrap/spec.md
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
