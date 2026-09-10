import { expect, type APIRequestContext } from '@playwright/test';

export type FrankenPhpModeExpectation = {
  mode: 'worker' | 'classic';
  /** When true (default for worker-safe), require reset_kernel === false. */
  resetKernel?: boolean;
  /** Expected FRANKENPHP_WORKER_NUM (worker-safe uses 1). */
  workerNum?: number | null;
  /** Require frankenphp_worker process flag (true only inside the real worker loop). */
  frankenphpWorker?: boolean;
};

/**
 * Assert `/health/live` runtime signals match the FrankenPHP contract under test.
 * @see docs/ops/FRANKENPHP-CODING.md
 */
export async function expectFrankenPhpRuntime(
  request: APIRequestContext,
  expected: FrankenPhpModeExpectation,
): Promise<void> {
  const res = await request.get('/health/live');
  expect(res.ok(), `GET /health/live → ${res.status()}`).toBeTruthy();
  const body = (await res.json()) as {
    status?: string;
    runtime?: {
      frankenphp_mode?: string;
      frankenphp_worker?: boolean;
      reset_kernel?: boolean;
      app_runtime_mode?: string | null;
      worker_num?: number | null;
    };
  };
  expect(body.status).toBe('ok');
  expect(body.runtime, 'runtime block missing on /health/live').toBeTruthy();
  const runtime = body.runtime!;

  expect(runtime.frankenphp_mode, 'frankenphp_mode').toBe(expected.mode);

  const expectWorkerProcess = expected.frankenphpWorker ?? expected.mode === 'worker';
  expect(runtime.frankenphp_worker, 'frankenphp_worker process flag').toBe(expectWorkerProcess);

  if (expected.resetKernel !== undefined) {
    expect(runtime.reset_kernel, 'reset_kernel').toBe(expected.resetKernel);
  }

  if (expected.workerNum !== undefined) {
    expect(runtime.worker_num, 'worker_num').toBe(expected.workerNum);
  }

  if (expected.mode === 'worker' && expected.resetKernel === false) {
    // Shared Kernel contract: APP_RUNTIME_MODE web=1&worker=1 (not worker=2 clone).
    expect(runtime.app_runtime_mode ?? '', 'app_runtime_mode for shared Kernel').toMatch(/worker=1/);
    expect(runtime.app_runtime_mode ?? '', 'must not be reset-kernel clone').not.toMatch(/worker=2/);
  }
}

/** Defaults for `make test-e2e-worker-safe` (overridable via PLAYWRIGHT_EXPECT_*). */
export function frankenPhpExpectationFromEnv(): FrankenPhpModeExpectation {
  const modeRaw = (process.env.PLAYWRIGHT_EXPECT_FRANKENPHP_MODE ?? 'worker').toLowerCase();
  const mode: 'worker' | 'classic' = modeRaw === 'classic' ? 'classic' : 'worker';
  const resetRaw = (process.env.PLAYWRIGHT_EXPECT_RESET_KERNEL ?? 'false').toLowerCase();
  const resetKernel = ['1', 'true', 'yes', 'on'].includes(resetRaw);
  const numRaw = process.env.PLAYWRIGHT_EXPECT_WORKER_NUM;
  const workerNum =
    numRaw === undefined || numRaw === ''
      ? mode === 'worker'
        ? 1
        : null
      : Number(numRaw);

  return {
    mode,
    resetKernel: mode === 'worker' ? resetKernel : false,
    workerNum: mode === 'worker' ? workerNum : null,
    frankenphpWorker: mode === 'worker',
  };
}
