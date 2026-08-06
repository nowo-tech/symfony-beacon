import { defineConfig, devices } from '@playwright/test';

/**
 * Browser E2E against the local Compose stack (FrankenPHP + Caddy TLS).
 *
 * Prerequisites: `make up` + `make seed` (demo admin). Optional: `make seed-sample`.
 * Base URL defaults to https://localhost:9447 (see DEFAULT_URI / HTTPS_PORT).
 */
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost:9447';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
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
        storageState: 'e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
      testIgnore: /auth\.setup\.ts/,
    },
  ],
});
