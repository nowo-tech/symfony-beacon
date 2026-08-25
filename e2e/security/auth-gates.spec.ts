import { test, expect } from '@playwright/test';
import { dismissCookieConsent, dismissProductTour, expectGuestPage, waitForPageLoader } from '../support/helpers';

/**
 * Auth security gates — login failure, disabled accounts, and public auth surfaces.
 * Happy-path magic/QR/reset completion lives in smoke/use-cases-auth-*.spec.ts.
 */
test.describe('Security — auth gates', () => {
  test('invalid credentials never leave the login page (UC-SEC-09)', async ({ browser }) => {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    try {
      await page.goto('/login');
      await dismissCookieConsent(page);
      await page.locator('input[name="login_form[_username]"]').fill('nobody@example.invalid');
      await page.locator('input[name="login_form[_password]"]').fill('definitely-wrong-password');
      await page
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await expect(page).toHaveURL(/\/login/);
      await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
      await expect(page).not.toHaveURL(/\/dashboard/);
    } finally {
      await context.close();
    }
  });

  test('disabled user cannot authenticate (UC-SEC-10)', async ({ page, browser }) => {
    test.setTimeout(90_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.sec.disabled.${suffix}@example.invalid`;
    const password = `E2eSecDis1!${suffix}`;

    await page.goto('/admin/users?new=1');
    await dismissProductTour(page);
    await waitForPageLoader(page);
    const form = page.locator('form').filter({ has: page.locator('input[name="admin_user[email]"]') });
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="admin_user[email]"]').fill(email);
    await form.locator('input[name="admin_user[displayName]"]').fill(`Sec Disabled ${suffix}`);
    await form.locator('input[name="admin_user[password]"]').fill(password);
    await form.locator('select[name="admin_user[role]"]').selectOption('user');
    const enabled = form.locator('input[name="admin_user[enabled]"]');
    if (await enabled.isChecked().catch(() => false)) {
      await enabled.uncheck();
    }
    const submit = form.locator('button.btn-primary[type="submit"], button[type="submit"]').first();
    await Promise.all([
      page.waitForURL((url) => url.pathname.replace(/\/$/, '') === '/admin/users' && !url.searchParams.has('new'), {
        timeout: 20_000,
      }).catch(() => undefined),
      submit.click(),
    ]);
    await waitForPageLoader(page);
    await page.goto(`/admin/users?q=${encodeURIComponent(email)}`);
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(email, { timeout: 20_000 });

    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const guest = await context.newPage();
    try {
      await guest.goto('/login');
      await dismissCookieConsent(guest);
      await guest.locator('input[name="login_form[_username]"]').fill(email);
      await guest.locator('input[name="login_form[_password]"]').fill(password);
      await guest
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await expect(guest).toHaveURL(/\/login/, { timeout: 20_000 });
      await expect(guest).not.toHaveURL(/\/dashboard/);
    } finally {
      await context.close();
    }
  });

  test('password reset and QR login surfaces stay public and do not expose dashboard (UC-SEC-11)', async ({
    browser,
  }) => {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    try {
      await expectGuestPage(page, '/reset-password');
      await expect(page).not.toHaveURL(/\/dashboard/);
      await expect(page.locator('body')).toBeVisible();

      await expectGuestPage(page, '/reset-password/complete');
      await expect(page).not.toHaveURL(/\/dashboard/);

      await expectGuestPage(page, '/login/qr');
      await expect(page).not.toHaveURL(/\/dashboard/);
      await expect(page.locator('body')).toBeVisible();

      await expectGuestPage(page, '/login/magic');
      await expect(page).not.toHaveURL(/\/dashboard/);
    } finally {
      await context.close();
    }
  });

  test('register stays closed when users already exist (UC-SEC-12)', async ({ browser }) => {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    try {
      await page.goto('/register');
      await dismissCookieConsent(page);
      await expect(page).toHaveURL(/\/login/);
      await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
    } finally {
      await context.close();
    }
  });
});
