import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  dismissCookieConsent,
  dismissProductTour,
  ensureDemoQrApprover,
  waitForPageLoader,
} from '../support/helpers';

const authFile = path.join(path.dirname(fileURLToPath(import.meta.url)), '../.auth/admin.json');

/**
 * UC-AUTH-22 — dual-context QR approve / deny.
 * Requires `nowo_auth_kit.qr_login.mode=enabled` and approver `phoneVerifiedAt`
 * (demo seed sets both for admin@symfony-beacon.local).
 *
 * Phone context reuses setup storageState to avoid login-throttle burn from mid-suite re-logins.
 * Deny is POST-only — use the approve page deny form (do not GET /deny).
 */
async function startQrChallenge(desk: import('@playwright/test').Page): Promise<{ id: string; approveHref: string }> {
  const response = await desk.goto('/login/qr', { waitUntil: 'domcontentloaded' });
  expect(response?.status() ?? 0, 'QR start must not be rate-limited (create_rate_limit: 0 locally)').toBeLessThan(400);
  await dismissCookieConsent(desk);
  await desk.waitForURL(/\/login\/qr\/[0-9a-f-]+/i, { timeout: 20_000 });
  const id = desk.url().match(/\/login\/qr\/([0-9a-f-]+)/i)?.[1];
  expect(id, 'challenge id').toBeTruthy();

  const openLink = desk.locator('.nowo-auth-kit__qr-url a[href*="approve"], a[href*="/login/qr/"][href*="approve"]').first();
  await expect(openLink).toBeVisible({ timeout: 15_000 });
  let approveHref = (await openLink.getAttribute('href')) || '';
  expect(approveHref, 'approve href from open-on-phone link').toBeTruthy();
  if (approveHref.startsWith('http')) {
    approveHref = approveHref.replace(/^https?:\/\/[^/]+/i, '');
  }

  return { id: id as string, approveHref };
}

async function openApproveOnPhone(mob: import('@playwright/test').Page, approveHref: string): Promise<void> {
  await ensureDemoQrApprover(mob);
  await mob.goto(approveHref);
  await dismissCookieConsent(mob);
  await dismissProductTour(mob);
  await waitForPageLoader(mob);
  await expect(mob.locator('form.nowo-auth-kit__qr-approve-form, form').filter({
    has: mob.locator('input[name="t"]'),
  }).first()).toBeVisible({ timeout: 15_000 });
}

test.describe('QR login approve / deny (dual context)', () => {
  test('authenticated device can deny a pending QR challenge (UC-AUTH-22 deny)', async ({ browser }) => {
    test.setTimeout(90_000);

    const desktop = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const phone = await browser.newContext({ ignoreHTTPSErrors: true, storageState: authFile });
    const desk = await desktop.newPage();
    const mob = await phone.newPage();

    try {
      const { id, approveHref } = await startQrChallenge(desk);
      await openApproveOnPhone(mob, approveHref);

      const denyForm = mob.locator('form[method="post"]').filter({
        has: mob.locator('button').filter({ hasText: /deny|deneg|rechaz|reject/i }),
      }).first();
      await expect(denyForm).toBeVisible({ timeout: 10_000 });
      await denyForm.locator('button[type="submit"]').click();
      await waitForPageLoader(mob);
      await expect(mob.locator('body')).toContainText(/denied|deneg|Login denied|acceso se deneg/i);

      let denied = false;
      for (let i = 0; i < 20; i++) {
        const status = await desk.request.get(`/login/qr/${id}/status`, { failOnStatusCode: false });
        if (status.status() < 500) {
          const payload = await status.json().catch(() => ({}));
          if (/denied|expired|consumed/i.test(String(payload.status ?? ''))) {
            denied = true;
            break;
          }
        }
        await desk.waitForTimeout(500);
      }
      expect(denied, 'desktop status becomes denied').toBeTruthy();
    } finally {
      await desktop.close();
      await phone.close();
    }
  });

  test('authenticated device can approve a pending QR challenge (UC-AUTH-22 approve)', async ({ browser }) => {
    test.setTimeout(120_000);

    const desktop = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const phone = await browser.newContext({ ignoreHTTPSErrors: true, storageState: authFile });
    const desk = await desktop.newPage();
    const mob = await phone.newPage();

    try {
      const { id, approveHref } = await startQrChallenge(desk);
      await openApproveOnPhone(mob, approveHref);

      const confirm = mob.locator('form.nowo-auth-kit__qr-approve-form button[type="submit"]').first();
      await expect(confirm).toBeVisible({ timeout: 15_000 });
      await confirm.click();
      await waitForPageLoader(mob);
      await expect(mob.locator('body')).toContainText(/approved|aprob/i);

      let approved = false;
      for (let i = 0; i < 20; i++) {
        const status = await desk.request.get(`/login/qr/${id}/status`, { failOnStatusCode: false });
        if (status.status() < 500) {
          const payload = await status.json().catch(() => ({}));
          if (/approved|consumed|complete/i.test(String(payload.status ?? ''))) {
            approved = true;
            break;
          }
        }
        await desk.waitForTimeout(500);
      }
      expect(approved, 'desktop status becomes approved/consumed').toBeTruthy();
    } finally {
      await desktop.close();
      await phone.close();
    }
  });
});
