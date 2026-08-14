import { test, expect } from '@playwright/test';
import { dismissCookieConsent, expectGuestPage } from '../support/helpers';

test.describe('Public / guest surfaces', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('root redirects toward login', async ({ page }) => {
    await page.goto('/');
    await dismissCookieConsent(page);
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
  });

  test('login form is usable', async ({ page }) => {
    await page.goto('/login');
    await dismissCookieConsent(page);
    await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
    await expect(page.locator('input[name="login_form[_password]"]')).toBeVisible();
    await expect(
      page.locator('form[name="login_form"] button[type="submit"], .nowo-auth-kit__panel button[type="submit"]').first(),
    ).toBeVisible();
  });

  test('localized login pages load', async ({ page }) => {
    for (const locale of ['en', 'es', 'de', 'fr']) {
      await expectGuestPage(page, `/${locale}/login`);
      await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
    }
  });

  test('magic login and QR login pages load', async ({ page }) => {
    await expectGuestPage(page, '/login/magic');
    await expect(page.locator('body')).toBeVisible();
    await expectGuestPage(page, '/login/qr');
    await expect(page.locator('body')).toBeVisible();
  });

  test('password reset page loads', async ({ page }) => {
    await expectGuestPage(page, '/reset-password');
    await expect(page.locator('body')).toBeVisible();
  });

  test('legal pages load (localized + bare redirects)', async ({ page }) => {
    for (const pageSlug of ['notice', 'privacy', 'terms', 'cookies']) {
      await expectGuestPage(page, `/en/legal/${pageSlug}`);
      await expect(page.getByRole('main')).toBeVisible();
      await expectGuestPage(page, `/legal/${pageSlug}`);
      await expect(page).toHaveURL(new RegExp(`/legal/${pageSlug}`));
    }
  });

  test('health endpoints respond', async ({ request }) => {
    const live = await request.get('/health/live');
    expect(live.status(), 'live').toBe(200);
    const ready = await request.get('/health/ready');
    expect(ready.status(), 'ready').toBeLessThan(500);
  });

  test('invalid credentials stay on login', async ({ page }) => {
    await page.goto('/login');
    await dismissCookieConsent(page);
    await page.locator('input[name="login_form[_username]"]').fill('nobody@example.invalid');
    await page.locator('input[name="login_form[_password]"]').fill('wrong-password-xyz');
    await page
      .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
      .first()
      .click();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
  });
});
