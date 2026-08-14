import { test, expect } from '@playwright/test';
import {
  beaconAuthHeader,
  dismissCookieConsent,
  dismissProductTour,
  ingestHttpBase,
  waitForPageLoader,
} from '../support/helpers';

async function createEphemeralProject(
  page: import('@playwright/test').Page,
  name: string,
): Promise<string> {
  await page.goto('/dashboard?new=1');
  await dismissProductTour(page);
  const dialog = page.locator('dialog[open], dialog.confirm-dialog[open], dialog:not([hidden])').first();
  if (!(await dialog.isVisible().catch(() => false))) {
    await page.locator('[data-tour="new-project"]').click();
  }
  await expect(page.locator('input[name="project[name]"]')).toBeVisible({ timeout: 10_000 });
  await page.locator('input[name="project[name]"]').fill(name);
  await page.locator('textarea[name="project[description]"]').fill('Ephemeral Playwright project');
  await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();
  await page.waitForURL(/\/projects\/([0-9a-f-]{36})/i, { timeout: 30_000 });
  await dismissProductTour(page);
  const match = page.url().match(/\/projects\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse project UUID from ${page.url()}`);
  }
  return match[1];
}

async function createEnabledUser(
  page: import('@playwright/test').Page,
  email: string,
  password: string,
  displayName: string,
): Promise<void> {
  await page.goto('/admin/users/new');
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
  await page.goto(`/admin/users?q=${encodeURIComponent(email)}`);
  await dismissProductTour(page);
  await expect(page.locator('table tbody tr, li, .panel').filter({ hasText: email }).first()).toBeVisible({
    timeout: 15_000,
  });
}

function minimalEnvelope(message: string): string {
  const eventId = crypto.randomUUID().replace(/-/g, '');
  return [
    JSON.stringify({ event_id: eventId, sent_at: new Date().toISOString() }),
    JSON.stringify({ type: 'event' }),
    JSON.stringify({
      event_id: eventId,
      timestamp: Date.now() / 1000,
      platform: 'php',
      level: 'error',
      logger: 'playwright.e2e',
      server_name: 'playwright',
      transaction: 'e2e.quota',
      environment: 'e2e',
      message,
      exception: {
        values: [
          {
            type: 'RuntimeException',
            value: message,
            stacktrace: {
              frames: [{ filename: 'e2e/use-cases-oos-closing.spec.ts', function: 'test', lineno: 1, in_app: true }],
            },
          },
        ],
      },
    }),
  ].join('\n');
}

test.describe('Out-of-scope closing — remaining automable flows', () => {
  test('ephemeral user self-anonymizes (UC-ACC-06)', async ({ browser, page }) => {
    const suffix = Date.now().toString(36);
    const email = `e2e.selfanon.${suffix}@example.invalid`;
    const password = `E2eSelfAnon!${suffix}Aa1`;

    await createEnabledUser(page, email, password, `SelfAnon ${suffix}`);

    const ctx = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const guest = await ctx.newPage();
    await guest.goto('/login');
    await dismissCookieConsent(guest);
    await guest.locator('input[name="login_form[_username]"]').fill(email);
    await guest.locator('input[name="login_form[_password]"]').fill(password);
    await guest
      .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
      .first()
      .click();
    await guest.waitForURL(/\/dashboard/, { timeout: 45_000 });
    await dismissProductTour(guest);

    await guest.goto('/account/privacy');
    await dismissProductTour(guest);
    await expect(guest.locator('[data-testid="account-privacy-anonymize"]')).toBeVisible();
    await guest.locator('[data-testid="account-privacy-anonymize-open"]').click();
    const dialog = guest.locator('dialog[open], .confirm-dialog[open]').last();
    await expect(dialog).toBeVisible({ timeout: 10_000 });
    const input = dialog.locator('[data-confirm-dialog-target="confirmInput"], #privacy-anonymize-confirm-input').first();
    await input.click();
    await input.fill('');
    await input.pressSequentially('ANONYMIZE', { delay: 20 });
    const submit = dialog.locator('[data-testid="account-privacy-anonymize-submit"], button[data-confirm-dialog-target="submit"]');
    await expect(submit).toBeEnabled({ timeout: 10_000 });
    await submit.click();
    await guest.waitForURL(/\/login/, { timeout: 30_000 });
    await expect(guest.locator('body')).toContainText(/anonymized|anonimiz/i);
    await ctx.close();
  });

  test('push preferences show unavailable shell without VAPID (UC-ACC-14)', async ({ page }) => {
    await page.goto('/account/display/notifications');
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="member-alerts-section"]')).toBeVisible({ timeout: 15_000 });
    // Without VAPID keys the subscribe toggle is not offered — assert the honest unavailable panel.
    const unavailable = page.locator('[data-testid="display-push-unavailable"]');
    if ((await unavailable.count()) > 0) {
      await expect(unavailable.first()).toBeVisible();
    } else {
      // Local env may have VAPID — then the toggle is the covered shell (actual browser Push subscribe remains external).
      await expect(
        page.locator(
          '[data-testid="member-alerts-push"] input[name*="pushNotificationsEnabled"], [data-testid="member-alerts-master-off-hint"]',
        ).first(),
      ).toBeAttached();
    }
  });

  test('login throttle locks after max failed attempts (UC-AUTH-09)', async ({ browser }) => {
    const suffix = Date.now().toString(36);
    const email = `e2e.throttle.${suffix}@example.invalid`;
    const ctx = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await ctx.newPage();

    for (let i = 0; i < 4; i++) {
      await page.goto('/login');
      await dismissCookieConsent(page);
      await page.locator('input[name="login_form[_username]"]').fill(email);
      await page.locator('input[name="login_form[_password]"]').fill('wrong-password');
      await page
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await page.waitForURL(/\/login/, { timeout: 20_000 });
      const body = await page.locator('body').innerText();
      expect(body, `attempt ${i + 1} should not be throttled yet`).not.toMatch(/Too many failed login attempts/i);
    }

    await page.goto('/login');
    await dismissCookieConsent(page);
    await page.locator('input[name="login_form[_username]"]').fill(email);
    await page.locator('input[name="login_form[_password]"]').fill('wrong-password');
    await page
      .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
      .first()
      .click();
    await page.waitForURL(/\/login/, { timeout: 20_000 });
    await expect(page.locator('.nowo-auth-kit__alert--error').first()).toContainText(
      /Too many failed login attempts|Demasiados intentos fallidos|Trop de tentatives|Zu viele fehlgeschlagene/i,
      { timeout: 15_000 },
    );
    await ctx.close();
  });

  test('daily quota exceeded returns 429 on ephemeral project (UC-ING-08 quota)', async ({ page, request }) => {
    const suffix = Date.now().toString(36);
    const name = `E2E Quota ${suffix}`;
    const uuid = await createEphemeralProject(page, name);

    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    const quota = page.locator('#project_governance_event_quota_daily, input[name="project_governance[event_quota_daily]"]');
    await expect(quota).toBeVisible({ timeout: 15_000 });
    await quota.fill('1');
    await page.locator('form').filter({ has: quota }).locator('button[type="submit"]').click();
    await waitForPageLoader(page);

    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const label = `e2e-quota-key-${suffix}`;
    const createForm = page.locator('form').filter({ has: page.locator('input[name="project_api_key_create[label]"]') });
    await createForm.locator('input[name="project_api_key_create[label]"]').fill(label);
    await createForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    const flash = page.locator('[data-testid="api-key-dsn-flash"]');
    await expect(flash).toBeVisible({ timeout: 15_000 });
    const flashText = await flash.innerText();
    const dsnMatch = flashText.match(/https?:\/\/([^:]+):([^@]+)@[^/\s]+\/([^\s]+)/i);
    expect(dsnMatch, 'DSN in flash').toBeTruthy();
    const publicKey = dsnMatch![1];
    const secretKey = dsnMatch![2];
    const projectRef = dsnMatch![3];

    const base = ingestHttpBase();
    const first = await request.post(`${base}/api/${projectRef}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(publicKey, secretKey),
      },
      data: minimalEnvelope(`quota-first-${suffix}`),
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect([200, 201, 202], await first.text()).toContain(first.status());

    // Quota counts persisted events — wait for async_ingest to write the event.
    let processed = false;
    for (let i = 0; i < 30; i++) {
      await page.goto(`/projects/${uuid}/issues`);
      await dismissProductTour(page);
      if ((await page.locator(`a[href*="/projects/${uuid}/issues/"]`).count()) > 0) {
        processed = true;
        break;
      }
      await page.waitForTimeout(1_000);
    }
    expect(processed, 'first envelope should appear as an issue').toBeTruthy();

    const second = await request.post(`${base}/api/${projectRef}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(publicKey, secretKey),
      },
      data: minimalEnvelope(`quota-second-${suffix}`),
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(second.status(), await second.text()).toBe(429);
    await expect(await second.text()).toMatch(/quota/i);
  });

  test('circuit breaker opens after forced HTTP failures then resume (UC-NOTIF-08)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const label = `e2e-circuit-${suffix}`;

    await page.goto('/admin/ops-defaults/notifications');
    await dismissProductTour(page);
    const threshold = page.locator(
      'input[name="instance_ops_defaults[notificationCircuitBreakerThreshold]"], #instance_ops_defaults_notificationCircuitBreakerThreshold',
    );
    await expect(threshold).toBeVisible({ timeout: 15_000 });
    const previous = await threshold.inputValue();
    await threshold.fill('1');
    await page.locator('form').filter({ has: threshold }).locator('button[type="submit"]').click();
    await waitForPageLoader(page);

    try {
      const uuid = await createEphemeralProject(page, `E2E Circuit ${suffix}`);
      await page.goto(`/projects/${uuid}/notifications/new`);
      await dismissProductTour(page);
      const form = page.getByRole('main').locator('form.notification-destination-form');
      await expect(form).toBeVisible();
      await form.locator('input[name="notification_destination[label]"]').fill(label);
      await form.locator('select[name="notification_destination[type]"]').selectOption('http');
      // Non-2xx POST → delivery failure (counts toward circuit; samples still attempt).
      await form.locator('input[name="notification_destination[endpointUrl]"]').fill('https://example.com/hooks/beacon-circuit-fail');
      const categories = form.locator('select[name="notification_destination[categories][]"]');
      if ((await categories.count()) > 0) {
        await categories.selectOption(['error'], { force: true }).catch(async () => {
          const ts = form.locator('.ts-control').first();
          if (await ts.isVisible().catch(() => false)) {
            await ts.click();
            await page.locator('.ts-dropdown .option').filter({ hasText: /error/i }).first().click({ force: true });
          }
        });
      }
      await form.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
      await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/alerts`), { timeout: 20_000 });

      const row = page.locator('#project-notification-destinations li').filter({ hasText: label }).first();
      await expect(row).toBeVisible({ timeout: 15_000 });
      const testBtn = row.locator('form[action*="/test"] button[type="submit"], button').filter({ hasText: /send test|enviar prueba/i }).first();
      await expect(testBtn).toBeVisible({ timeout: 10_000 });
      await testBtn.click();
      await waitForPageLoader(page);
      await expect(page.locator('body')).toContainText(/queued|encolad|test|prueba|sample|muestra/i);

      let resumeVisible = false;
      for (let i = 0; i < 40; i++) {
        await page.goto(`/projects/${uuid}/settings/alerts`);
        await dismissProductTour(page);
        const resume = page.locator(`form[action*="/notifications/"][action*="/resume"] button[type="submit"]`).first();
        if (await resume.isVisible().catch(() => false)) {
          await resume.click();
          await waitForPageLoader(page);
          await expect(page.locator('body')).toContainText(/resumed|reanud/i);
          resumeVisible = true;
          break;
        }
        await page.waitForTimeout(1_500);
      }
      expect(resumeVisible, 'circuit should open and Resume should succeed').toBeTruthy();
    } finally {
      await page.goto('/admin/ops-defaults/notifications');
      await dismissProductTour(page);
      const restore = page.locator(
        'input[name="instance_ops_defaults[notificationCircuitBreakerThreshold]"], #instance_ops_defaults_notificationCircuitBreakerThreshold',
      );
      if (await restore.isVisible().catch(() => false)) {
        await restore.fill(previous || '5');
        await page.locator('form').filter({ has: restore }).locator('button[type="submit"]').click();
        await waitForPageLoader(page);
      }
    }
  });
});
