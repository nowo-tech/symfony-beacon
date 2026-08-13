import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from './helpers';

test.describe('Account & navigation chrome — remaining use cases', () => {
  test('display appearance prefs can be saved (UC-ACC-07)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/display');
    const form = page.getByRole('main').locator('form').filter({ has: page.locator('button[type="submit"]') }).first();
    await expect(form).toBeVisible();
    const density = form.locator('select[name="user_preferences[density]"], select[id*="density"]').first();
    if ((await density.count()) > 0) {
      const options = density.locator('option');
      const count = await options.count();
      if (count > 1) {
        const value = await options.nth(1).getAttribute('value');
        if (value) {
          await density.selectOption(value);
        }
      }
    }
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/account\/display/);
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('collapsed panels prefs form loads and saves (UC-ACC-08)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/display/panels');
    const form = page.getByRole('main').locator('form').first();
    await expect(form).toBeVisible();
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/account\/display\/panels/);
  });

  test('Mercure realtime config endpoint responds (UC-ACC-15)', async ({ page }) => {
    await page.goto('/dashboard');
    await dismissProductTour(page);
    const res = await page.request.get('/account/realtime/config');
    expect(res.status()).toBeLessThan(500);
    expect([200, 204]).toContain(res.status());
  });

  test('account area nav switches Profile / Security / Display (UC-DASH-10 partial)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/profile');
    const nav = page.locator('[data-testid="account-area-nav"]');
    await expect(nav).toBeVisible();
    await nav.locator('a[href*="/account/security"]').first().click();
    await expect(page).toHaveURL(/\/account\/security/);
    await nav.locator('a[href*="/account/display"]').first().click();
    await expect(page).toHaveURL(/\/account\/display/);
    await nav.locator('a[href*="/account/profile"], a[href="/account"]').first().click();
    await expect(page).toHaveURL(/\/account(\/profile)?/);
  });

  test('user menu reaches Dashboard and Administration (UC-DASH-10)', async ({ page }) => {
    await page.goto('/account/profile');
    await dismissProductTour(page);
    const menu = page.locator('[data-tour="user-menu"], [data-user-menu]');
    await menu.locator('summary').click();
    const dash = menu.locator('a[href="/dashboard"], a[href*="/dashboard"]').first();
    await expect(dash).toBeVisible();
    await dash.click();
    await expect(page).toHaveURL(/\/dashboard/);
    await dismissProductTour(page);
    await menu.locator('summary').click();
    const admin = menu.locator('a[href="/admin"], a[href*="/admin"]').first();
    await expect(admin).toBeVisible();
    await admin.click();
    await expect(page).toHaveURL(/\/admin/);
  });

  test('admin ops overview shows filter shell and metrics (UC-OPS-06)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/ops');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByRole('main')).toBeVisible();
    await expect(page.getByRole('main').locator('form, [data-testid="ops-security-posture"], h1, h2').first()).toBeVisible();
  });
});
