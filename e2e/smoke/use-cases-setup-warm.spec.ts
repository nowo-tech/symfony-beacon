import { test, expect } from '@playwright/test';
import { dismissCookieConsent } from '../support/helpers';

/**
 * Warm-install setup surface (setup.done already present).
 * Never GET /setup or POST /setup/api/advance — those can leave incomplete progress
 * and gate the app (see UC-SETUP-01 hygiene).
 */
test.describe('Setup warm install APIs & done page', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('setup progress JSON is idle on warm install (UC-SETUP-04 GET)', async ({ request }) => {
    const res = await request.get('/setup/api/progress', { failOnStatusCode: false });
    expect(res.status(), await res.text()).toBe(200);
    const body = (await res.json()) as {
      phase?: string;
      percent?: number;
      profile?: string;
      current_step_id?: string | null;
    };
    expect(body.phase).toBe('idle');
    expect(body.percent ?? 0).toBe(0);
    expect(body.current_step_id ?? null).toBeNull();
    expect(body.profile).toBeTruthy();
  });

  test('localized setup progress matches unlocalized (UC-SETUP-04)', async ({ request }) => {
    const bare = await request.get('/setup/api/progress');
    const en = await request.get('/en/setup/api/progress');
    expect(bare.status()).toBe(200);
    expect(en.status()).toBe(200);
    expect(await bare.json()).toEqual(await en.json());
  });

  test('setup advance rejects GET without mutating progress (UC-SETUP-04 route)', async ({ request }) => {
    const before = await (await request.get('/setup/api/progress')).json();
    const res = await request.get('/setup/api/advance', { failOnStatusCode: false });
    // Route is POST-only; GET must not advance the wizard.
    expect(res.status()).toBeGreaterThanOrEqual(400);
    expect(res.status()).toBeLessThan(500);
    const after = await (await request.get('/setup/api/progress')).json();
    expect(after).toEqual(before);
    expect(after.phase).toBe('idle');
  });

  test('setup done page loads on warm install (UC-SETUP-05)', async ({ page }) => {
    const res = await page.goto('/en/setup/done', { waitUntil: 'domcontentloaded' });
    await dismissCookieConsent(page);
    expect(res, 'response').not.toBeNull();
    expect(res!.status()).toBe(200);
    await expect(page.getByRole('heading', { name: /setup complete/i })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
  });

  test('unlocalized setup done also loads (UC-SETUP-05)', async ({ page }) => {
    const res = await page.goto('/setup/done', { waitUntil: 'domcontentloaded' });
    await dismissCookieConsent(page);
    expect(res, 'response').not.toBeNull();
    expect(res!.status()).toBe(200);
    await expect(page.locator('h1').first()).toBeVisible();
  });
});
