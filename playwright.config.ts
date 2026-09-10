import { defineConfig, devices } from '@playwright/test';

/**
 * Browser E2E against the local Compose stack (FrankenPHP + Caddy TLS).
 *
 * Dogfood: `make up` + `make seed` (+ sample) → `make test-e2e` (default :9447).
 * Isolated DB: `make up-e2e` + `make ready-e2e` → `make test-e2e-isolated` (:9460 / app_e2e).
 * Cold install: `make wipe-e2e-cold` + `make up-e2e-cold` → `make test-e2e-cold` (:9461 / app_e2e_cold).
 */
const cold = process.env.PLAYWRIGHT_COLD === '1';
const isolated = process.env.PLAYWRIGHT_ISOLATED === '1';
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

export default defineConfig({
  testDir: './e2e',
  fullyParallel: !cold,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: cold ? 1 : workers,
  timeout: cold ? 600_000 : 60_000,
  expect: { timeout: cold ? 60_000 : 15_000 },
  reporter: process.env.CI ? [['github'], ['list']] : [['list'], ['html', { open: 'never' }]],
  outputDir: 'test-results',
  use: {
    baseURL,
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    locale: 'en-US',
    navigationTimeout: cold ? 120_000 : 45_000,
    actionTimeout: 15_000,
    // Prefer DOM ready over full load — FrankenPHP/WSL often stalls on "load".
    // Specs that need networkidle still override per-call.
  },
  projects: cold
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
          testIgnore: [/auth\.setup\.ts/, /cold\//],
        },
      ],
});
