import { test, expect } from '@playwright/test';
import {
  dismissCookieConsent,
  dismissProductTour,
  expectAuthenticatedPage,
  gotoStable,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

/**
 * Closes remaining automable Out-of-scope rows where a browser assertion is honest:
 * UC-AUTH-23 (IdP authorize redirect), UC-OPS-10 (Twig Inspector toolbar),
 * UC-SETUP-03 (seeded demo project visible), UC-ACC-23 (push HTTP with VAPID).
 * OAuth callback / browser Push permission / cold setup stay Out of scope.
 */
test.describe('Remaining OOS closing', () => {
  test('social Continue redirects toward IdP authorize URL (UC-AUTH-23 redirect)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const providerSlug = `e2e-redir-${suffix}`;
    const label = `E2E Redir ${suffix}`;
    const authorizeHost = 'idp.example.invalid';

    await page.goto('/admin/social-login/new');
    await dismissProductTour(page);
    const form = page.locator('form').filter({
      has: page.locator('input[name="social_login_credential[client_id]"]'),
    });
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="social_login_credential[provider]"]').fill(providerSlug);
    await form.locator('input[name="social_login_credential[label]"]').fill(label);
    await form.locator('input[name="social_login_credential[client_id]"]').fill(`e2e-client-${suffix}`);
    await form.locator('input[name="social_login_credential[client_secret]"]').fill(`e2e-secret-${suffix}`);
    const enabled = form.locator('input[name="social_login_credential[enabled]"]');
    if ((await enabled.count()) > 0 && !(await enabled.isChecked().catch(() => false))) {
      await enabled.check();
    }
    await form.locator('input[name="social_login_credential[authorize_url]"]').fill(`https://${authorizeHost}/oauth/authorize`);
    await form.locator('input[name="social_login_credential[token_url]"]').fill(`https://${authorizeHost}/oauth/token`);
    await form.locator('input[name="social_login_credential[userinfo_url]"]').fill(`https://${authorizeHost}/oauth/userinfo`);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(label);

    try {
      const guestCtx = await browser.newContext({
        ignoreHTTPSErrors: true,
        storageState: { cookies: [], origins: [] },
      });
      const guest = await guestCtx.newPage();
      try {
        await guest.goto('/login');
        await dismissCookieConsent(guest);
        const link = guest.locator(`a[href*="/login/social/${providerSlug}"]`).first();
        await expect(link).toBeVisible({ timeout: 15_000 });

        // Capture the outbound authorize request (DNS will fail for *.example.invalid).
        const authorizeReq = guest.waitForRequest(
          (r) => r.url().includes(`${authorizeHost}/oauth/authorize`),
          { timeout: 20_000 },
        );
        await link.click();
        const req = await authorizeReq;
        expect(req.url()).toMatch(/client_id=/i);
        expect(req.url()).toMatch(/redirect_uri=/i);
      } finally {
        await guestCtx.close();
      }
    } finally {
      await page.goto('/admin/social-login');
      await dismissProductTour(page);
      const row = page.locator('tr').filter({ hasText: label }).first();
      if (await row.isVisible().catch(() => false)) {
        page.once('dialog', (d) => d.accept().catch(() => undefined));
        await row.locator(`form[action*="/social-login/${providerSlug}/delete"] button[type="submit"]`).click();
        await waitForPageLoader(page);
      }
    }
  });

  test('Twig Inspector toolbar control is present in dev (UC-OPS-10)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    await dismissProductTour(page);
    // Symfony Web Debug Toolbar + Twig Inspector block (dev / require-dev only).
    const toolbar = page.locator('.sf-toolbar, #sfwdt');
    if ((await toolbar.count()) === 0) {
      test.skip(true, 'Web Debug Toolbar absent (prod-like APP_ENV)');
    }
    await expect(page.locator('#_twig_inspector__icon, [id*="twig_inspector"]').first()).toBeVisible({
      timeout: 20_000,
    });
  });

  test('seeded demo project is listed after seed (UC-SETUP-03)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    await dismissProductTour(page);
    const uuid = await resolveDemoProjectUuid(page);
    expect(uuid).toMatch(/^[0-9a-f-]{36}$/i);
    await expect(page.getByRole('main')).toContainText(/Symfony Beacon|symfony-beacon/i);
  });

  test('push subscribe then unsubscribe HTTP when VAPID configured (UC-ACC-23)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/account/display/notifications');
    await dismissProductTour(page);

    const unavailable = page.locator('[data-testid="display-push-unavailable"]');
    if (await unavailable.isVisible().catch(() => false)) {
      test.skip(true, 'VAPID not configured — set VAPID_* in .env and recreate php');
    }

    // Preference gate on subscribe — flip Live switch (and verify) before HTTP.
    const pushSwitch = page.locator('[data-testid="member-alerts-push"] input[type="checkbox"]').first();
    await expect(pushSwitch).toBeVisible({ timeout: 15_000 });
    if (!(await pushSwitch.isChecked())) {
      await pushSwitch.locator('xpath=ancestor::label[1]').click().catch(async () => {
        await pushSwitch.click({ force: true });
      });
      await expect(pushSwitch).toBeChecked({ timeout: 15_000 });
      // Give Live Component a beat to flush.
      await page.waitForTimeout(1_500);
    }
    const uuid = await resolveDemoProjectUuid(page);
    await gotoStable(page, `/projects/${uuid}/issues`);
    await dismissProductTour(page);
    const firstIssue = page.locator('a[href*="/issues/"]').first();
    if (!(await firstIssue.isVisible().catch(() => false))) {
      test.skip(true, 'No sample issue to host realtime CSRF bootstrap — run make seed-sample');
    }
    await firstIssue.click();
    await waitForPageLoader(page);

    const bootstrap = page.locator('[data-controller="issue-realtime"]').first();
    await expect(bootstrap).toBeVisible({ timeout: 15_000 });
    const csrf = await bootstrap.getAttribute('data-issue-realtime-csrf-token-value');
    const subscribeUrl = await bootstrap.getAttribute('data-issue-realtime-subscribe-url-value');
    const unsubscribeUrl = await bootstrap.getAttribute('data-issue-realtime-unsubscribe-url-value');
    expect(csrf).toBeTruthy();
    expect(subscribeUrl).toBeTruthy();
    expect(unsubscribeUrl).toBeTruthy();

    const endpoint = `https://fcm.googleapis.com/fcm/send/e2e-push-${Date.now().toString(36)}`;
    // Dummy Web Push key material (not real credentials; storage only in this flow).
    const keys = {
      p256dh: 'e2e-dummy-p256dh-key-material',
      auth: 'e2e-dummy-auth-key',
    };

    const sub = await page.request.post(subscribeUrl!, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf!,
      },
      data: { endpoint, keys, contentEncoding: 'aes128gcm' },
    });
    expect(sub.status(), await sub.text()).toBe(200);
    expect((await sub.json()).ok).toBeTruthy();

    const unsub = await page.request.post(unsubscribeUrl!, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf!,
      },
      data: { endpoint },
    });
    expect(unsub.status(), await unsub.text()).toBe(200);
    expect((await unsub.json()).ok).toBeTruthy();
  });
});
