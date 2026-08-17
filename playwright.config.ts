import { defineConfig, devices } from '@playwright/test';

/**
 * Browser E2E against the local Compose stack (FrankenPHP + Caddy TLS).
 *
 * Dogfood: `make up` + `make seed` (+ sample) → `make test-e2e` (default :9447).
 * Isolated DB: `make up-e2e` + `make ready-e2e` → `make test-e2e-isolated` (:9460 / app_e2e).
 */
const isolated = process.env.PLAYWRIGHT_ISOLATED === '1';
const baseURL =
  process.env.PLAYWRIGHT_BASE_URL ?? (isolated ? 'https://localhost:9460' : 'https://localhost:9447');
const authFile =
  process.env.PLAYWRIGHT_AUTH_FILE ??
  (isolated ? 'e2e/.auth/admin.e2e.json' : 'e2e/.auth/admin.json');

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1,
  timeout: 60_000,
  expect: { timeout: 15_000 },
  reporter: process.env.CI ? [['github'], ['list']] : [['list'], ['html', { open: 'never' }]],
  outputDir: 'test-results',
  use: {
    baseURL,
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    locale: 'en-US',
    navigationTimeout: 45_000,
    actionTimeout: 15_000,
    // Prefer DOM ready over full load — FrankenPHP/WSL often stalls on "load".
    // Specs that need networkidle still override per-call.
  },
  projects: [
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
      testIgnore: /auth\.setup\.ts/,
    },
  ],
});
