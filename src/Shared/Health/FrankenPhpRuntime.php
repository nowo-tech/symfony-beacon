<?php

declare(strict_types=1);

namespace App\Shared\Health;

/**
 * Non-secret FrankenPHP / Symfony Runtime signals for probes and worker-safety checks.
 *
 * @see docs/ops/FRANKENPHP-CODING.md
 */
final class FrankenPhpRuntime
{
    /**
     * @return array{
     *     frankenphp_mode: string,
     *     frankenphp_worker: bool,
     *     reset_kernel: bool,
     *     app_runtime_mode: string|null,
     *     worker_num: int|null
     * }
     */
    public static function snapshot(?callable $env = null): array
    {
        $read = $env ?? static function (string $key): ?string {
            // Prefer $_SERVER (request/process env). Avoid $_ENV writes elsewhere; reads here are probe-only.
            if (\array_key_exists($key, $_SERVER) && \is_scalar($_SERVER[$key])) {
                return (string) $_SERVER[$key];
            }

            return null;
        };

        $modeRaw = strtolower(trim((string) ($read('FRANKENPHP_MODE') ?? 'classic')));
        $mode = \in_array($modeRaw, ['classic', 'worker'], true) ? $modeRaw : 'classic';

        $workerFlag = strtolower(trim((string) ($read('FRANKENPHP_WORKER') ?? '')));
        $frankenphpWorker = '1' === $workerFlag || 'true' === $workerFlag;

        $resetRaw = strtolower(trim((string) ($read('FRANKENPHP_RESET_KERNEL') ?? 'false')));
        $resetKernel = \in_array($resetRaw, ['1', 'true', 'yes', 'on'], true);

        $runtimeMode = $read('APP_RUNTIME_MODE');
        $runtimeMode = null !== $runtimeMode && '' !== $runtimeMode ? $runtimeMode : null;

        $workerNumRaw = $read('FRANKENPHP_WORKER_NUM');
        $workerNum = null;
        if (null !== $workerNumRaw && '' !== $workerNumRaw && ctype_digit($workerNumRaw)) {
            $workerNum = (int) $workerNumRaw;
        }

        return [
            'frankenphp_mode' => $mode,
            'frankenphp_worker' => $frankenphpWorker,
            'reset_kernel' => $resetKernel,
            'app_runtime_mode' => $runtimeMode,
            'worker_num' => 'worker' === $mode ? $workerNum : null,
        ];
    }
}
