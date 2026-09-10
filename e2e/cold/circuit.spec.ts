import { test, expect } from '@playwright/test';
import { dismissCookieConsent, dismissProductTour } from '../support/helpers';
import {
  COLD_ADMIN_EMAIL,
  COLD_ADMIN_PASSWORD,
  getSetupProgress,
  runFreshInstallCircuit,
} from '../support/setup-cold';

/**
 * Cold-start circuit (UC-SETUP-07 → UC-SETUP-01 → UC-AUTH-10 via wizard admin).
 *
 * Requires disposable stack: `make wipe-e2e-cold && make up-e2e-cold && make test-e2e-cold`.
 * Never run against warm `app_e2e` / dogfood (would mutate setup progress).
 */
test.describe.configure({ mode: 'serial' });

test.describe('Cold-start setup circuit', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('AuthKit /login and /register gate to /setup without token (UC-SETUP-07)', async ({ page }) => {
    for (const path of ['/login', '/register'] as const) {
      await page.goto(path, { waitUntil: 'domcontentloaded' });
      await expect(page).toHaveURL(/\/setup(\?|$|\/)/);
      expect(page.url()).not.toMatch(/[?&]token=/);
    }
  });

  test('fresh_install guided API completes (UC-SETUP-01)', async ({ request }) => {
    const before = await getSetupProgress(request);
    if (!('redirectedHome' in before)) {
      expect(['idle', 'running', 'waiting_input', 'failed'].includes(before.phase ?? 'idle')).toBeTruthy();
    }

    const done = await runFreshInstallCircuit(request);
    expect(done.phase).toBe('completed');
    expect(done.percent ?? 0).toBeGreaterThanOrEqual(100);

    const after = await getSetupProgress(request);
    if ('redirectedHome' in after) {
      expect(after.redirectedHome).toBe(true);
      return;
    }
    expect(['completed', 'idle'].includes(after.phase ?? '')).toBeTruthy();
  });

  test('wizard admin can sign in to dashboard (UC-AUTH-10)', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page).not.toHaveURL(/\/setup(\?|$|\/)/);
    await dismissCookieConsent(page);

    await page.locator('input[name="login_form[_username]"]').fill(COLD_ADMIN_EMAIL);
    await page.locator('input[name="login_form[_password]"]').fill(COLD_ADMIN_PASSWORD);
    await page
      .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
      .first()
      .click();

    await page.waitForURL(/\/dashboard(\?|$)/, { timeout: 60_000 });
    await dismissProductTour(page);
    await expect(page.locator('body')).toBeVisible();
  });
});
