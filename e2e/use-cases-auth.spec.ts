import { test, expect } from '@playwright/test';
import { dismissCookieConsent, expectGuestPage } from './helpers';

test.describe('Auth & branded errors — use cases', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('register redirects to login when users already exist (UC-AUTH-11)', async ({ page }) => {
    await page.goto('/register');
    await dismissCookieConsent(page);
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
  });

  test('localized register also redirects when users exist (UC-AUTH-11)', async ({ page }) => {
    await page.goto('/en/register');
    await dismissCookieConsent(page);
    await expect(page).toHaveURL(/\/(en\/)?login/);
  });

  test('unknown path shows branded error without 5xx (UC-OPS-04)', async ({ page }) => {
    const res = await page.goto('/this-path-does-not-exist-e2e-404', { waitUntil: 'domcontentloaded' });
    await dismissCookieConsent(page);
    expect(res, 'response').not.toBeNull();
    expect(res!.status()).toBe(404);
    await expect(page.locator('body')).toBeVisible();
    // Branded Twig error (not the raw Symfony exception dump).
    await expect(page.locator('main, [role="main"], h1').first()).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
  });

  test('setup path does not 5xx for guests (UC-SETUP-01 smoke)', async ({ page }) => {
    await expectGuestPage(page, '/setup');
    await expect(page.locator('body')).toBeVisible();
  });
});
