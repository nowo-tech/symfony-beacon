import { test, expect } from '@playwright/test';
import { dismissCookieConsent } from '../support/helpers';

/**
 * Warm-install setup surface (setup.done / durable setup_completed_at present).
 * Never GET /setup or POST /setup/api/advance — those can leave incomplete progress
 * and gate the app (see UC-SETUP-01 hygiene).
 *
 * SiteBackup durable_done (1.12+) may 302 wizard + `/setup/api/*` to home when the
 * instance is already complete — that is still a valid warm-install signal.
 */
test.describe('Setup warm install APIs & done page', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('setup progress JSON is idle on warm install (UC-SETUP-04 GET)', async ({ request }) => {
    const res = await request.get('/setup/api/progress', { failOnStatusCode: false, maxRedirects: 0 });
    if ([301, 302, 303, 307, 308].includes(res.status())) {
      expect(res.headers()['location'] ?? '').toMatch(/\/($|\?)/);
      return;
    }
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
    const bare = await request.get('/setup/api/progress', { failOnStatusCode: false, maxRedirects: 0 });
    const en = await request.get('/en/setup/api/progress', { failOnStatusCode: false, maxRedirects: 0 });
    expect(bare.status()).toBe(en.status());
    if ([301, 302, 303, 307, 308].includes(bare.status())) {
      expect(bare.headers()['location'] ?? '').toMatch(/\/($|\?|en\/?$)/);
      expect(en.headers()['location'] ?? '').toMatch(/\/($|\?|en\/?$)/);
      return;
    }
    expect(bare.status()).toBe(200);
    expect(en.status()).toBe(200);
    expect(await bare.json()).toEqual(await en.json());
  });

  test('setup advance rejects GET without mutating progress (UC-SETUP-04 route)', async ({ request }) => {
    const res = await request.get('/setup/api/advance', { failOnStatusCode: false, maxRedirects: 0 });
    // POST-only route, or durable-done closes the wizard API with a redirect — never 2xx advance.
    expect(res.status()).toBeGreaterThanOrEqual(300);
    expect(res.status()).toBeLessThan(500);
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
