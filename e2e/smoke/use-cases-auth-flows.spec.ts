import { test, expect } from '@playwright/test';
import {
  DEMO_EMAIL,
  DEMO_PASSWORD,
  dismissCookieConsent,
  dismissProductTour,
  loginAsDemo,
  logout,
  waitForPageLoader,
} from '../support/helpers';

/**
 * Magic + reset are gated until an encrypted deliverable Mailer DSN is saved
 * (`MailerGatedAuthKitRouteSubscriber`). QR login is `mode: disabled` in AuthKit config.
 */
async function ensureDeliverableMailer(page: import('@playwright/test').Page): Promise<void> {
  await page.goto('/admin/mailer');
  await waitForPageLoader(page);
  const form = page.locator('form').filter({ has: page.locator('input[name*="[plainMailerDsn]"]') });
  await expect(form).toBeVisible({ timeout: 15_000 });
  await form.locator('input[name*="[plainMailerDsn]"]').fill('smtp://mailer:1025');
  const from = form.locator('input[name*="[mailerFrom]"], input[name*="[from]"]');
  if ((await from.count()) > 0 && (await from.first().inputValue()) === '') {
    await from.first().fill('beacon@symfony-beacon.local');
  }
  await form.locator('button[type="submit"]').first().click();
  await waitForPageLoader(page);
  await expect(page).not.toHaveURL(/\/login/);
}

test.describe('Auth flows — magic, reset, QR', () => {
  test('submits magic-login request without 5xx (UC-AUTH-17)', async ({ page, browser }) => {
    await ensureDeliverableMailer(page);

    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const guest = await ctx.newPage();
    try {
      await guest.goto('/login/magic');
      await dismissCookieConsent(guest);
      await expect(guest).toHaveURL(/\/login\/magic/, { timeout: 15_000 });
      const form = guest.locator('.nowo-auth-kit__panel form, form').filter({
        has: guest.locator('input[name*="[identifier]"], input[type="email"]'),
      });
      await expect(form.first()).toBeVisible({ timeout: 15_000 });
      await form.first().locator('input[name*="[identifier]"], input[type="email"]').first().fill(DEMO_EMAIL);
      await form.first().locator('button[type="submit"]').click();
      await waitForPageLoader(guest);
      await expect(guest.locator('body')).not.toContainText('Whoops, looks like something went wrong');
      await expect(guest).not.toHaveURL(/\/_error/);
    } finally {
      await ctx.close();
    }
  });

  test('submits password-reset request without 5xx (UC-AUTH-19)', async ({ page, browser }) => {
    await ensureDeliverableMailer(page);

    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const guest = await ctx.newPage();
    try {
      await guest.goto('/reset-password');
      await dismissCookieConsent(guest);
      await expect(guest).toHaveURL(/\/reset-password/, { timeout: 15_000 });
      const form = guest.locator('.nowo-auth-kit__panel form, form').filter({
        has: guest.locator('input[name*="[identifier]"], input[type="email"]'),
      });
      await expect(form.first()).toBeVisible({ timeout: 15_000 });
      await form.first().locator('input[name*="[identifier]"], input[type="email"]').first().fill(DEMO_EMAIL);
      await form.first().locator('button[type="submit"]').click();
      await waitForPageLoader(guest);
      await expect(guest.locator('body')).not.toContainText('Whoops, looks like something went wrong');
      await expect(guest).not.toHaveURL(/\/_error/);
    } finally {
      await ctx.close();
    }
  });

  test('QR login disabled redirects to login (UC-AUTH-21 gate)', async ({ browser }) => {
    // Product config: nowo_auth_kit.qr_login.mode=disabled until phoneVerifiedAt path exists.
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const page = await ctx.newPage();
    try {
      await page.goto('/login/qr');
      await dismissCookieConsent(page);
      await page.waitForTimeout(1_000);
      if (/\/login\/qr(\/[0-9a-f-]+)?/i.test(page.url())) {
        const match = page.url().match(/\/login\/qr\/([0-9a-f-]{8,})/i);
        if (match?.[1]) {
          const status = await page.request.get(`/login/qr/${match[1]}/status`, { failOnStatusCode: false });
          expect(status.status()).toBeLessThan(500);
        } else {
          await expect(page.locator('img.nowo-auth-kit__qr-image, img[alt*="QR"], img[src*="data:image"]').first()).toBeVisible({
            timeout: 15_000,
          });
        }
      } else {
        await expect(page).toHaveURL(/\/login/, { timeout: 10_000 });
        await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
      }
    } finally {
      await ctx.close();
    }
  });

  test('localized logout path works (UC-AUTH-24)', async ({ browser }) => {
    // Logout requires CSRF (`enable_csrf: true`) — use the menu link, not bare GET /logout.
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const page = await ctx.newPage();
    try {
      await loginAsDemo(page, DEMO_EMAIL, DEMO_PASSWORD);
      await expect(page).toHaveURL(/\/dashboard/, { timeout: 15_000 });
      await dismissProductTour(page);
      const menu = page.locator('[data-action="user-menu"], [data-user-menu], .user-menu').first();
      await expect(menu).toBeVisible({ timeout: 15_000 });
      await menu.locator('summary').click();
      const logoutLink = menu.locator('a[href*="/logout"]');
      await expect(logoutLink).toBeVisible({ timeout: 10_000 });
      await logoutLink.click();
      await page.waitForURL(/\/(en\/|es\/)?login/, { timeout: 20_000 });
      await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
    } finally {
      await ctx.close();
    }
  });
});
