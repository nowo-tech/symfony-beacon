import { test, expect } from '@playwright/test';
import { dismissCookieConsent, dismissProductTour, expectAuthenticatedPage, expectGuestPage } from './helpers';

test.describe('Auth chrome — guest', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('remember-me checkbox is present and can be checked (UC-AUTH-08)', async ({ page }) => {
    await page.goto('/login');
    await dismissCookieConsent(page);
    const remember = page
      .locator(
        'input[name="login_form[_remember_me]"], input[name*="remember"], input[type="checkbox"][id*="remember"]',
      )
      .first();
    await expect(remember).toBeVisible();
    await remember.check();
    await expect(remember).toBeChecked();
  });

  test('password reveal control is present on login (UC-AUTH-15)', async ({ page }) => {
    await page.goto('/login');
    await dismissCookieConsent(page);
    // PasswordToggleBundle uses a span[data-controller="password-toggle"], not a <button>.
    const toggle = page.locator('.form-password-toggle [data-controller="password-toggle"]').first();
    await expect(toggle).toBeVisible();
    const password = page.locator('input[name="login_form[_password]"]');
    await expect(password).toHaveAttribute('type', 'password');
    await toggle.click();
    await expect(password).toHaveAttribute('type', 'text');
  });

  test('guest login has no social buttons when providers unset (UC-AUTH-14)', async ({ page }) => {
    await expectGuestPage(page, '/login');
    await expect(page.locator('a[href*="connect/"], button:has-text("Google"), a:has-text("GitHub")')).toHaveCount(0);
  });

  test('maintenance preview returns branded 503 (UC-OPS-05)', async ({ request }) => {
    const res = await request.get('/_maintenance_preview');
    // Preview intentionally serves the maintenance page as HTTP 503.
    expect(res.status()).toBe(503);
  });
});

test.describe('Auth chrome — authenticated', () => {
  test('account security exposes password toggle / strength (UC-AUTH-15)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/security');
    await expect(
      page.locator('[data-controller="password-toggle"], .password-strength-widget, input[type="password"]').first(),
    ).toBeVisible();
  });

  test('admin maintenance panel loads (UC-OPS-05)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/maintenance');
    await dismissProductTour(page);
    await expect(page.getByRole('main')).toBeVisible();
  });
});
