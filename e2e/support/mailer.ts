import { expect, type Page } from '@playwright/test';
import { gotoStable, waitForPageLoader } from './helpers';

/**
 * SMTP DSN for Admin → Mailer in E2E.
 *
 * CI starts Compose profile `mail` (service hostname `mailer`).
 * Local dogfood prefers shared `mailpit` on server_network — override with
 * PLAYWRIGHT_MAILER_DSN when needed.
 */
export function playwrightMailerSmtpDsn(): string {
  if (process.env.PLAYWRIGHT_MAILER_DSN) {
    return process.env.PLAYWRIGHT_MAILER_DSN;
  }
  if (process.env.CI) {
    return 'smtp://mailer:1025';
  }
  return 'smtp://mailpit:1025';
}

/**
 * Persist a deliverable encrypted Mailer DSN so AuthKit magic-login / password-reset
 * routes are not redirected to login by MailerGatedAuthKitRouteSubscriber.
 */
export async function ensureDeliverableMailer(page: Page): Promise<void> {
  await gotoStable(page, '/admin/mailer');
  await waitForPageLoader(page);
  const form = page.locator('form').filter({ has: page.locator('input[name*="[plainMailerDsn]"]') });
  await expect(form).toBeVisible({ timeout: 15_000 });
  await form.locator('input[name*="[plainMailerDsn]"]').fill(playwrightMailerSmtpDsn());
  const from = form.locator('input[name*="[mailerFrom]"], input[name*="[from]"]');
  if ((await from.count()) > 0 && (await from.first().inputValue()) === '') {
    await from.first().fill('beacon@symfony-beacon.local');
  }
  await form.locator('button[type="submit"]').first().click();
  await waitForPageLoader(page);
  await expect(page).not.toHaveURL(/\/login/);
}
