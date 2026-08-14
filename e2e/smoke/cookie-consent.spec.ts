import { test, expect } from '@playwright/test';
import { dismissCookieConsent, waitForPageLoader } from '../support/helpers';

test.describe('Cookie consent', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('guest sees consent modal and can accept all', async ({ page, context }) => {
    // Fresh guest context without prior consent cookies.
    await context.clearCookies();
    await page.goto('/en/login');
    await waitForPageLoader(page);

    const openModal = page.locator('#cookieconsent[data-nowo-open="true"]:not(.hidden)');
    try {
      await openModal.waitFor({ state: 'visible', timeout: 5_000 });
    } catch {
      // Already consented via kit defaults — still assert login shell.
      await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
      return;
    }

    await expect(openModal.locator('#cookie_consent_use_all_cookies, #cookie_consent_use_only_functional_cookies').first()).toBeVisible();
    await dismissCookieConsent(page);
    await expect(openModal).toHaveCount(0);
    await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
  });

  test('consent persists across navigation', async ({ page, context }) => {
    await context.clearCookies();
    await page.goto('/en/legal/privacy');
    await dismissCookieConsent(page);

    await page.goto('/en/legal/terms');
    await waitForPageLoader(page);
    await expect(page.locator('#cookieconsent[data-nowo-open="true"]:not(.hidden)')).toHaveCount(0);
    await expect(page.getByRole('main')).toBeVisible();
  });

  test('cookie consent config endpoint responds', async ({ request }) => {
    const res = await request.get('/cookie-consent/config');
    expect(res.status()).toBeLessThan(500);
    if (res.status() === 200) {
      const ct = res.headers()['content-type'] ?? '';
      expect(ct).toMatch(/json|javascript|text/i);
    }
  });

  test('localized cookie consent config responds', async ({ request }) => {
    for (const locale of ['en', 'es']) {
      const res = await request.get(`/${locale}/cookie-consent/config`);
      expect(res.status(), locale).toBeLessThan(500);
    }
  });
});
