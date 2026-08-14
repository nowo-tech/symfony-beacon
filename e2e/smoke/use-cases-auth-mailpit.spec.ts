import { test, expect } from '@playwright/test';
import {
  DEMO_EMAIL,
  dismissCookieConsent,
  dismissProductTour,
  gotoStable,
  waitForPageLoader,
} from '../support/helpers';
import {
  mailpitDeleteAll,
  mailpitIsReachable,
  mailpitWaitForLink,
  requireMailpitOrSkip,
} from '../support/mailpit';

/**
 * UC-AUTH-18 / UC-AUTH-20 — complete magic-login and password-reset via Mailpit.
 * Prerequisites: `make mailpit` + deliverable Admin → Mailer DSN `smtp://mailer:1025`.
 */
async function ensureDeliverableMailer(page: import('@playwright/test').Page): Promise<void> {
  await gotoStable(page, '/admin/mailer');
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

async function createEnabledUser(
  page: import('@playwright/test').Page,
  email: string,
  password: string,
  displayName: string,
): Promise<void> {
  await gotoStable(page, '/admin/users?new=1');
  await dismissProductTour(page);
  const form = page.locator('form').filter({ has: page.locator('input[name="admin_user[email]"]') });
  await expect(form).toBeVisible({ timeout: 15_000 });
  await form.locator('input[name="admin_user[email]"]').fill(email);
  await form.locator('input[name="admin_user[displayName]"]').fill(displayName);
  await form.locator('input[name="admin_user[password]"]').fill(password);
  await form.locator('select[name="admin_user[role]"]').selectOption('user');
  const enabled = form.locator('input[name="admin_user[enabled]"]');
  if ((await enabled.count()) > 0 && !(await enabled.isChecked().catch(() => false))) {
    await enabled.check();
  }
  await form.locator('button[type="submit"]').click();
  await waitForPageLoader(page);
}

test.describe('Auth flows — Mailpit magic + reset complete', () => {
  test.beforeEach(async () => {
    requireMailpitOrSkip(await mailpitIsReachable(), 'Mailpit not reachable — run `make mailpit` (UI :18026)');
  });

  test('completes magic-login from Mailpit link (UC-AUTH-18)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    await ensureDeliverableMailer(page);
    await mailpitDeleteAll();

    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const guest = await ctx.newPage();
    try {
      await guest.goto('/login/magic');
      await dismissCookieConsent(guest);
      const identifier = guest.locator('input[name="magic_login_request_form[identifier]"]');
      await expect(identifier).toBeVisible({ timeout: 15_000 });
      await identifier.fill(DEMO_EMAIL);
      await guest.locator('form[name="magic_login_request_form"] button[type="submit"], .nowo-auth-kit__panel form button[type="submit"]').first().click();
      await waitForPageLoader(guest);

      const checkPath = await mailpitWaitForLink({
        toAddress: DEMO_EMAIL,
        // Locale-aware subjects (en "magic sign-in" / es "enlace mágico" / …).
        subjectIncludes: /magic|mágic|enlace|sign-?in|login|acceso|anmelden|connexion/i,
        linkPattern: /https?:\/\/[^\s"'<>]+\/(?:[a-z]{2}\/)?login\/magic\/check[^\s"'<>]*/i,
      });

      await guest.goto(checkPath);
      await dismissCookieConsent(guest);
      await waitForPageLoader(guest);

      const confirm = guest.locator('form.nowo-auth-kit__form, form').filter({
        has: guest.locator('button[type="submit"]'),
      }).first();
      await expect(confirm).toBeVisible({ timeout: 15_000 });
      await confirm.locator('button[type="submit"]').click();
      await guest.waitForURL(/\/dashboard/, { timeout: 45_000 });
      await dismissProductTour(guest);
      await expect(guest).toHaveURL(/\/dashboard/);
    } finally {
      await ctx.close();
    }
  });

  test('completes password reset from Mailpit link (UC-AUTH-20)', async ({ page, browser }) => {
    test.setTimeout(150_000);
    await ensureDeliverableMailer(page);

    const suffix = Date.now().toString(36);
    const email = `e2e.reset.${suffix}@example.invalid`;
    const oldPassword = `E2eOld1!${suffix}`;
    const newPassword = `E2eNew1!${suffix}`;
    await createEnabledUser(page, email, oldPassword, `Reset ${suffix}`);
    await mailpitDeleteAll();

    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const guest = await ctx.newPage();
    try {
      await guest.goto('/reset-password');
      await dismissCookieConsent(guest);
      const requestForm = guest.locator('form[name="reset_password_request_form"], .nowo-auth-kit__panel form').filter({
        has: guest.locator('input[name*="[identifier]"]'),
      }).first();
      await expect(requestForm).toBeVisible({ timeout: 15_000 });
      await requestForm.locator('input[name*="[identifier]"]').fill(email);
      await requestForm.locator('button[type="submit"]').click();
      await waitForPageLoader(guest);

      const resetPath = await mailpitWaitForLink({
        toAddress: email,
        subjectIncludes: /reset|password|contraseña|passwort|passe|passworda|restablec/i,
        linkPattern: /https?:\/\/[^\s"'<>]+\/(?:[a-z]{2}\/)?reset-password\/reset\/[^\s"'<>]+/i,
      });

      await guest.goto(resetPath);
      await dismissCookieConsent(guest);
      await waitForPageLoader(guest);

      const resetForm = guest.locator('form').filter({
        has: guest.locator('input[name*="[password]"]'),
      }).first();
      await expect(resetForm).toBeVisible({ timeout: 15_000 });
      await resetForm.locator('input[name*="[password]"]:not([name*="confirm"])').fill(newPassword);
      const confirmField = resetForm.locator('input[name*="[password_confirm]"], input[name*="confirm"]');
      await expect(confirmField.first()).toBeVisible();
      await confirmField.first().fill(newPassword);
      await resetForm.locator('button[type="submit"]').click();
      await waitForPageLoader(guest);

      // After reset, sign in with the new password.
      await guest.goto('/login');
      await dismissCookieConsent(guest);
      await guest.locator('input[name="login_form[_username]"]').fill(email);
      await guest.locator('input[name="login_form[_password]"]').fill(newPassword);
      await guest
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await guest.waitForURL(/\/dashboard/, { timeout: 45_000 });
      await dismissProductTour(guest);
      await expect(guest).toHaveURL(/\/dashboard/);
    } finally {
      await ctx.close();
    }
  });
});
