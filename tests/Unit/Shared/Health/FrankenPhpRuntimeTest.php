<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Health;

use App\Shared\Health\FrankenPhpRuntime;
use PHPUnit\Framework\TestCase;

final class FrankenPhpRuntimeTest extends TestCase
{
    public function testWorkerSharedKernelSnapshot(): void
    {
        $env = static fn (string $key): ?string => match ($key) {
            'FRANKENPHP_MODE' => 'worker',
            'FRANKENPHP_WORKER' => '1',
            'FRANKENPHP_RESET_KERNEL' => 'false',
            'APP_RUNTIME_MODE' => 'web=1&worker=1',
            'FRANKENPHP_WORKER_NUM' => '1',
            default => null,
        };

        self::assertSame([
            'frankenphp_mode' => 'worker',
            'frankenphp_worker' => true,
            'reset_kernel' => false,
            'app_runtime_mode' => 'web=1&worker=1',
            'worker_num' => 1,
        ], FrankenPhpRuntime::snapshot($env));
    }

    public function testClassicSnapshot(): void
    {
        $env = static fn (string $key): ?string => match ($key) {
            'FRANKENPHP_MODE' => 'classic',
            'FRANKENPHP_WORKER' => '',
            'FRANKENPHP_RESET_KERNEL' => 'false',
            default => null,
        };

        $snap = FrankenPhpRuntime::snapshot($env);
        self::assertSame('classic', $snap['frankenphp_mode']);
        self::assertFalse($snap['frankenphp_worker']);
        self::assertFalse($snap['reset_kernel']);
        self::assertNull($snap['app_runtime_mode']);
        self::assertNull($snap['worker_num']);
    }

    public function testResetKernelTrueMapsToWorker2Semantics(): void
    {
        $env = static fn (string $key): ?string => match ($key) {
            'FRANKENPHP_MODE' => 'worker',
            'FRANKENPHP_WORKER' => '1',
            'FRANKENPHP_RESET_KERNEL' => 'true',
            'APP_RUNTIME_MODE' => 'web=1&worker=2',
            default => null,
        };

        $snap = FrankenPhpRuntime::snapshot($env);
        self::assertTrue($snap['reset_kernel']);
        self::assertSame('web=1&worker=2', $snap['app_runtime_mode']);
    }
}
