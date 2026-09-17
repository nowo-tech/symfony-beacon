import { defineConfig, devices } from '@playwright/test';

/**
 * Browser E2E against the local Compose stack (FrankenPHP + Caddy TLS).
 *
 * Dogfood: `make up` + `make seed` (+ sample) → `make test-e2e` (default :9447).
 * Isolated DB: `make up-e2e` + `make ready-e2e` → `make test-e2e-isolated` (:9460 / app_e2e).
 * Cold install: `make wipe-e2e-cold` + `make up-e2e-cold` → `make test-e2e-cold` (:9461 / app_e2e_cold).
 * Docs manual: `make docs-manual-screenshots` / `make docs-manual-screenshots-setup`.
 * Mailpit delivery: `make test-e2e-mailpit` (PLAYWRIGHT_MAILPIT=1).
 */
const cold = process.env.PLAYWRIGHT_COLD === '1';
const manual = process.env.PLAYWRIGHT_MANUAL === '1';
const mailpit = process.env.PLAYWRIGHT_MAILPIT === '1';
const isolated = process.env.PLAYWRIGHT_ISOLATED === '1';
/** FrankenPHP worker probe (`make test-e2e-worker-safe`) — do not ignore e2e/worker. */
const workerSuite = process.env.PLAYWRIGHT_WORKER_SUITE === '1';
const baseURL =
  process.env.PLAYWRIGHT_BASE_URL ??
  (cold ? 'https://localhost:9461' : isolated ? 'https://localhost:9460' : 'https://localhost:9447');
const authFile =
  process.env.PLAYWRIGHT_AUTH_FILE ??
  (isolated ? 'e2e/.auth/admin.e2e.json' : 'e2e/.auth/admin.json');
/** Multi-worker against smoke DB (REQ-QA-003). Serial debug: PLAYWRIGHT_WORKERS=1 */
const workers = process.env.PLAYWRIGHT_WORKERS
  ? Number(process.env.PLAYWRIGHT_WORKERS)
  : process.env.CI
    ? 2
    : 4;

const desktopManual = {
  ...devices['Desktop Chrome'],
  viewport: { width: 1440, height: 900 },
};

export default defineConfig({
  testDir: './e2e',
  fullyParallel: !cold && !manual && !mailpit,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: cold || manual || mailpit ? 1 : workers,
  timeout: cold || manual ? 600_000 : mailpit ? 120_000 : 60_000,
  expect: { timeout: cold ? 60_000 : 15_000 },
  reporter: process.env.CI ? [['github'], ['list']] : [['list'], ['html', { open: 'never' }]],
  outputDir: 'test-results',
  use: {
    serviceWorkers: 'block',
    baseURL,
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    locale: 'en-US',
    navigationTimeout: cold ? 120_000 : 45_000,
    actionTimeout: 15_000,
  },
  projects:
    cold && manual
      ? [
          {
            name: 'manual-setup',
            testMatch: /manual\/capture-setup\.spec\.ts/,
            use: {
              ...desktopManual,
              storageState: { cookies: [], origins: [] },
            },
          },
        ]
      : cold
        ? [
            {
              name: 'cold',
              testMatch: /cold\/.*\.spec\.ts/,
              use: {
                ...devices['Desktop Chrome'],
                storageState: { cookies: [], origins: [] },
              },
            },
          ]
        : manual
          ? [
              {
                name: 'setup',
                testMatch: /auth\.setup\.ts/,
              },
              {
                name: 'manual',
                testMatch: /manual\/capture-screens\.spec\.ts/,
                use: {
                  ...desktopManual,
                  storageState: authFile,
                },
                dependencies: ['setup'],
              },
            ]
          : [
              {
                name: 'setup',
                testMatch: /auth\.setup\.ts/,
              },
              {
                name: 'chromium',
                use: {
                  ...devices['Desktop Chrome'],
                  storageState: authFile,
                },
                dependencies: ['setup'],
                // cold/manual: dedicated Make targets; worker/: only when PLAYWRIGHT_WORKER_SUITE=1.
                testIgnore: workerSuite
                  ? [/auth\.setup\.ts/, /cold\//, /manual\//, ...(mailpit ? [] : [/use-cases-auth-mailpit/])]
                  : [/auth\.setup\.ts/, /cold\//, /manual\//, /worker\//, ...(mailpit ? [] : [/use-cases-auth-mailpit/])],
              },
            ],
});
