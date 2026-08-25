import { test, expect, type Page } from '@playwright/test';
import {
  DEMO_PASSWORD,
  dismissCookieConsent,
  dismissProductTour,
  gotoStable,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';
import { addProjectMember, createEnabledUser, loginAsUser } from '../support/security';

/**
 * Limit / abuse guards beyond UC-SEC-13..39 — validation edges, IDOR on keys,
 * forged CSRF on tokens/shares, AJAX preference abuse, and confirmation skips.
 */

async function createEphemeralProject(page: Page, name: string): Promise<string> {
  await gotoStable(page, '/dashboard?new=1');
  await dismissProductTour(page);
  const dialog = page.locator('dialog[open], dialog.confirm-dialog[open], dialog:not([hidden])').first();
  if (!(await dialog.isVisible().catch(() => false))) {
    await page.locator('[data-tour="new-project"], [data-action="new-project"]').first().click();
  }
  await expect(page.locator('input[name="project[name]"]')).toBeVisible({ timeout: 10_000 });
  await page.locator('input[name="project[name]"]').fill(name);
  await page.locator('textarea[name="project[description]"]').fill('Ephemeral Playwright limit-guards');
  await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();
  await page.waitForURL(/\/projects\/([0-9a-f-]{36})/i, { timeout: 30_000 });
  await dismissProductTour(page);
  const match = page.url().match(/\/projects\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse project UUID from ${page.url()}`);
  }
  return match[1];
}

async function resolveUserUuidFromAdmin(page: Page, email: string): Promise<string> {
  await page.goto(`/admin/users?q=${encodeURIComponent(email)}`);
  await waitForPageLoader(page);
  const row = page.locator('tr').filter({ hasText: email }).first();
  await expect(row).toBeVisible({ timeout: 15_000 });
  const activity = row.locator('a[href*="/admin/users/"][href*="/activity"]').first();
  await expect(activity).toBeVisible({ timeout: 10_000 });
  const href = await activity.getAttribute('href');
  const match = href?.match(/\/admin\/users\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse user UUID from ${href ?? '(null)'}`);
  }
  return match[1];
}

test.describe('Limit guards — validation edges, IDOR, AJAX abuse', () => {
  test('share days out of range are rejected (UC-SEC-40)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const share = page.locator('[data-testid="share-links"]');
    await expect(share).toBeVisible({ timeout: 15_000 });
    const createForm = share.locator('form').filter({
      has: page.locator('input[name="project_share_create[days]"]'),
    });
    const token = await createForm.locator('input[name="project_share_create[_token]"]').inputValue();

    for (const days of ['0', '99', '-1']) {
      const res = await page.request.post(`/projects/${uuid}/settings/share-links`, {
        form: {
          'project_share_create[days]': days,
          'project_share_create[max_uses]': '1',
          'project_share_create[_token]': token,
        },
        failOnStatusCode: false,
        maxRedirects: 0,
      });
      expect(res.status(), `days=${days} → ${await res.text()}`).toBeLessThan(500);
      expect(res.status(), `days=${days}`).not.toBe(200);
    }

    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="share-url"]')).toHaveCount(0);
    await expect(page.locator('body')).toContainText(
      /invalid|inválid|between|entre|range|1 and 30|1 y 30|days|días|must|debe|csrf|security token/i,
    );
  });

  test('forged CSRF on API key revoke leaves the key active (UC-SEC-41)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const label = `e2e-csrf-key-${Date.now().toString(36)}`;
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const createForm = page.locator('form').filter({
      has: page.locator('input[name="project_api_key_create[label]"]'),
    });
    await createForm.locator('input[name="project_api_key_create[label]"]').fill(label);
    await createForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);

    const row = page.locator('li').filter({ hasText: label }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    const revokeForm = row.locator('form[action*="/keys/"][action*="/revoke"]').first();
    await expect(revokeForm).toBeAttached();
    const action = (await revokeForm.getAttribute('action')) ?? '';
    const keyMatch = action.match(/\/keys\/(\d+)\/revoke/);
    expect(keyMatch?.[1], action).toBeTruthy();

    const res = await page.request.post(action, {
      form: { _token: 'forged-csrf-token' },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeGreaterThanOrEqual(400);
    expect(res.status()).toBeLessThan(500);

    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const after = page.locator('li').filter({ hasText: label }).first();
    await expect(after).toBeVisible();
    await expect(after).not.toContainText(/inactive|inactiv/i);
    await expect(after.locator('form[action*="/revoke"]')).toBeVisible();
  });

  test('empty project name is rejected (UC-SEC-42)', async ({ page }) => {
    await gotoStable(page, '/dashboard?new=1');
    await dismissProductTour(page);
    if (!(await page.locator('input[name="project[name]"]').isVisible().catch(() => false))) {
      await page.locator('[data-tour="new-project"], [data-action="new-project"]').first().click();
    }
    const form = page.locator('form').filter({ has: page.locator('input[name="project[name]"]') }).first();
    await expect(form).toBeVisible({ timeout: 10_000 });
    const token = await form.locator('input[name="project[_token]"]').inputValue();

    const res = await page.request.post('/projects/new', {
      form: {
        'project[name]': '   ',
        'project[description]': 'should-not-create',
        'project[_token]': token,
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    // Stay on create / dashboard — never land on a new project detail.
    if ([302, 303, 307].includes(res.status())) {
      const location = res.headers().location ?? '';
      expect(location, location).not.toMatch(/\/projects\/[0-9a-f-]{36}(\/|$)/i);
    } else {
      expect(res.status()).toBeGreaterThanOrEqual(400);
    }

    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);
    await expect(page.locator('a[href*="/projects/"]').filter({ hasText: /should-not-create/i })).toHaveCount(0);
  });

  test('garbage and unknown project paths return branded 404 (UC-SEC-43 / UC-SEC-57)', async ({ page }) => {
    for (const path of [
      '/projects/not-a-uuid',
      '/projects/../../etc/passwd',
      '/projects/00000000-0000-4000-8000-000000000099',
    ]) {
      const response = await page.goto(path, { waitUntil: 'domcontentloaded', timeout: 45_000 });
      await dismissProductTour(page);
      expect(response, path).not.toBeNull();
      expect(response!.status(), `${path} status`).toBe(404);
      await expect(page.locator('body')).toContainText(/404|not found|no encontrad|introuvable/i);
      await expect(page.locator('body')).not.toContainText(/Whoops, looks like something went wrong/);
    }
  });

  test('theme and content-width AJAX reject bad payloads and forged CSRF (UC-SEC-44 / UC-SEC-53)', async ({
    page,
  }) => {
    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);
    const root = page.locator('[data-theme-sync-token], [data-content-width-sync-token]').first();
    await expect(root).toBeAttached({ timeout: 15_000 });
    const themeToken = (await page.locator('[data-theme-sync-token]').first().getAttribute('data-theme-sync-token')) ?? '';
    const widthToken =
      (await page.locator('[data-content-width-sync-token]').first().getAttribute('data-content-width-sync-token')) ?? '';
    expect(themeToken.length, 'theme CSRF').toBeGreaterThan(8);
    expect(widthToken.length, 'content-width CSRF').toBeGreaterThan(8);

    const badTheme = await page.request.post('/account/theme', {
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': themeToken },
      data: JSON.stringify({ theme: 'neon-void' }),
      failOnStatusCode: false,
    });
    expect(badTheme.status(), await badTheme.text()).toBe(400);
    await expect(await badTheme.text()).toMatch(/invalid_theme/i);

    const forgedTheme = await page.request.post('/account/theme', {
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': 'forged' },
      data: JSON.stringify({ theme: 'dark' }),
      failOnStatusCode: false,
    });
    expect(forgedTheme.status(), await forgedTheme.text()).toBe(403);
    await expect(await forgedTheme.text()).toMatch(/invalid_csrf/i);

    const badWidth = await page.request.post('/account/content-width', {
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': widthToken },
      data: JSON.stringify({ contentWidth: 'ultra-wide' }),
      failOnStatusCode: false,
    });
    expect(badWidth.status(), await badWidth.text()).toBe(400);
    await expect(await badWidth.text()).toMatch(/invalid_content_width/i);

    const forgedWidth = await page.request.post('/account/content-width', {
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': 'forged' },
      data: JSON.stringify({ contentWidth: 'full' }),
      failOnStatusCode: false,
    });
    expect(forgedWidth.status(), await forgedWidth.text()).toBe(403);
    await expect(await forgedWidth.text()).toMatch(/invalid_csrf/i);
  });

  test('wrong privacy anonymize confirmation does not anonymize (UC-SEC-45)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.limit.anon.${suffix}@example.invalid`;
    const password = `E2eLimitAnon1!${suffix}`;
    await createEnabledUser(page, email, password, `Limit Anon ${suffix}`);

    const { context, page: user } = await loginAsUser(browser, email, password);
    try {
      await user.goto('/account/privacy');
      await dismissProductTour(user);
      await expect(user.locator('body')).toContainText(/anonymize|anonimiz|privacy|privacidad/i);

      const res = await user.request.post('/account/privacy/anonymize', {
        form: {
          confirmation: 'WRONG',
          _token: 'forged-or-wrong',
        },
        failOnStatusCode: false,
        maxRedirects: 0,
      });
      expect(res.status(), await res.text()).toBeGreaterThanOrEqual(400);
      expect(res.status()).toBeLessThan(500);

      await user.goto('/account/privacy');
      await dismissProductTour(user);
      await expect(user).not.toHaveURL(/\/login/);
      await expect(user.locator('body')).toContainText(email);
      await expect(user.locator('body')).not.toContainText(/Whoops, looks like something went wrong/);
    } finally {
      await context.close();
    }
  });

  test('viewer cannot escalate own role via forged POST (UC-SEC-46)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.limit.viewer.${suffix}@example.invalid`;
    const password = `E2eLimitView1!${suffix}`;
    await createEnabledUser(page, email, password, `Limit Viewer ${suffix}`);
    const userUuid = await resolveUserUuidFromAdmin(page, email);
    const projectUuid = await resolveDemoProjectUuid(page);
    await addProjectMember(page, projectUuid, email, 'viewer');

    const { context, page: viewer } = await loginAsUser(browser, email, password);
    try {
      const res = await viewer.request.post(`/projects/${projectUuid}/members/${userUuid}/role`, {
        form: {
          role: 'full',
          _token: 'forged',
        },
        failOnStatusCode: false,
        maxRedirects: 0,
      });
      const status = res.status();
      expect(status, await res.text()).toBeGreaterThanOrEqual(400);
      expect(status).toBeLessThan(500);
      expect([403, 400, 422]).toContain(status);
    } finally {
      await context.close();
    }

    await page.goto(`/projects/${projectUuid}/settings/access`);
    await dismissProductTour(page);
    const row = page.locator('li').filter({ hasText: email }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    // Role badge only — the edit-role dialog lists every assignable option (Full/Owner/…).
    const badge = row.locator('.badge').first();
    await expect(badge).toBeVisible({ timeout: 10_000 });
    await expect(badge).toHaveText(/viewer|lector|lecteur|betrachter/i);
  });

  test('file:// notification endpoint is rejected (UC-SEC-47)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const label = `e2e-file-url-${Date.now().toString(36)}`;
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('http');
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill('file:///etc/passwd');
    const categories = form.locator('select[name="notification_destination[categories][]"]');
    if ((await categories.count()) > 0) {
      await categories.selectOption(['error'], { force: true }).catch(() => undefined);
    }
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/notifications/(new|\\S+/edit)`));
    await expect(page.locator('body')).toContainText(
      /does not match|no coincide|endpoint_invalid|invalid|inválid|not allowed|no permitido|ssrf|http/i,
    );
    await page.goto(`/projects/${uuid}/settings/alerts`);
    await dismissProductTour(page);
    await expect(page.locator('#project-notification-destinations')).not.toContainText(label);
  });

  test('clear-history without slide confirm is rejected (UC-SEC-48)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    await page.goto(`/projects/${uuid}/settings/danger`);
    await dismissProductTour(page);
    const clearForm = page.locator('form').filter({
      has: page.locator('input[name="project_clear_history[_token]"]'),
    });
    await expect(clearForm.first()).toBeAttached({ timeout: 15_000 });
    const token = await clearForm.locator('input[name="project_clear_history[_token]"]').first().inputValue();

    const res = await page.request.post(`/projects/${uuid}/clear-history`, {
      form: {
        // Valid CSRF, but slide-to-confirm checkbox omitted / unchecked.
        'project_clear_history[_token]': token,
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeGreaterThanOrEqual(400);
    expect(res.status()).toBeLessThan(500);

    const after = await page.goto(`/projects/${uuid}/issues/${issueUuid}`, { waitUntil: 'domcontentloaded' });
    await dismissProductTour(page);
    expect(after, 'issue must still exist').not.toBeNull();
    expect(after!.status()).toBeLessThan(400);
  });

  test('guest POST cannot create share links (UC-SEC-49)', async ({ page, browser }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const guest = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    try {
      const res = await guest.request.post(`/projects/${uuid}/settings/share-links`, {
        form: {
          'project_share_create[days]': '7',
          'project_share_create[max_uses]': '1',
          'project_share_create[_token]': 'forged',
        },
        failOnStatusCode: false,
        maxRedirects: 0,
      });
      const status = res.status();
      const location = res.headers().location ?? '';
      const denied =
        status === 401 ||
        status === 403 ||
        ((status === 302 || status === 303 || status === 307) && /login/i.test(location));
      expect(denied, `guest share create ${status} Location=${location}`).toBeTruthy();
    } finally {
      await guest.close();
    }
  });

  test('API key revoke under another project UUID is 404 (UC-SEC-50)', async ({ page }) => {
    test.setTimeout(90_000);
    const demoUuid = await resolveDemoProjectUuid(page);
    const label = `e2e-idor-key-${Date.now().toString(36)}`;
    await page.goto(`/projects/${demoUuid}/settings/access`);
    await dismissProductTour(page);
    const createForm = page.locator('form').filter({
      has: page.locator('input[name="project_api_key_create[label]"]'),
    });
    await createForm.locator('input[name="project_api_key_create[label]"]').fill(label);
    await createForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);

    const row = page.locator('li').filter({ hasText: label }).first();
    const revokeForm = row.locator('form[action*="/keys/"][action*="/revoke"]').first();
    const action = (await revokeForm.getAttribute('action')) ?? '';
    const keyMatch = action.match(/\/keys\/(\d+)\/revoke/);
    expect(keyMatch?.[1], action).toBeTruthy();
    const keyId = keyMatch![1];
    const token = await revokeForm.locator('input[name="_token"]').inputValue();

    const otherUuid = await createEphemeralProject(page, `E2E Key IDOR ${Date.now().toString(36)}`);
    const res = await page.request.post(`/projects/${otherUuid}/keys/${keyId}/revoke`, {
      form: { _token: token },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBe(404);

    await page.goto(`/projects/${demoUuid}/settings/access`);
    await dismissProductTour(page);
    const still = page.locator('li').filter({ hasText: label }).first();
    await expect(still).toBeVisible();
    await expect(still).not.toContainText(/inactive|inactiv/i);
  });

  test('password confirm mismatch does not change the password (UC-SEC-51)', async ({ page }) => {
    await page.goto('/account/security');
    await dismissProductTour(page);
    const form = page.locator('form').filter({
      has: page.locator('input[name="user_preferences[plainPassword]"]'),
    });
    await expect(form).toBeVisible({ timeout: 15_000 });
    const strong = `E2eMismatch1!${Date.now().toString(36).slice(-4)}`;
    await form.locator('input[name="user_preferences[currentPassword]"]').fill(DEMO_PASSWORD);
    await form.locator('input[name="user_preferences[plainPassword]"]').fill(strong);
    await form.locator('input[name="user_preferences[plainPassword_confirm]"]').fill(`${strong}-different`);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/account\/security/);
    await expect(page.locator('body')).toContainText(/match|coincid|mismatch|no coinciden|iguales|same/i);

    // Demo password must still authenticate (no silent change).
    const context = await page.context().browser()!.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const guest = await context.newPage();
    try {
      await guest.goto('/login');
      await dismissCookieConsent(guest);
      await guest.locator('input[name="login_form[_username]"]').fill(
        process.env.PLAYWRIGHT_DEMO_EMAIL ?? 'admin@symfony-beacon.local',
      );
      await guest.locator('input[name="login_form[_password]"]').fill(DEMO_PASSWORD);
      await guest
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await expect(guest).toHaveURL(/\/dashboard/, { timeout: 45_000 });
    } finally {
      await context.close();
    }
  });

  test('invalid quiet-hours timezone is rejected (UC-SEC-52)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const label = `e2e-bad-tz-${Date.now().toString(36)}`;
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('http');
    await form
      .locator('input[name="notification_destination[endpointUrl]"]')
      .fill('https://example.com/hooks/beacon-e2e');
    const categories = form.locator('select[name="notification_destination[categories][]"]');
    if ((await categories.count()) > 0) {
      await categories.selectOption(['error'], { force: true }).catch(() => undefined);
    }
    const quiet = form.locator('input[name="notification_destination[quietHoursEnabled]"]');
    if (!(await quiet.isChecked().catch(() => false))) {
      await quiet.check({ force: true });
    }
    await form.locator('input[name="notification_destination[quietHoursTimezone]"]').fill('Not/A_Real_Zone');
    await form.locator('input[name="notification_destination[quietHoursStart]"]').fill('22:00');
    await form.locator('input[name="notification_destination[quietHoursEnd]"]').fill('06:00');
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/notifications/(new|\\S+/edit)`));
    await expect(page.locator('body')).toContainText(/timezone|zona horaria|invalid|inválid|time zone/i);
    await page.goto(`/projects/${uuid}/settings/alerts`);
    await dismissProductTour(page);
    await expect(page.locator('#project-notification-destinations')).not.toContainText(label);
  });

  test('forged CSRF on share-link create is rejected (UC-SEC-54)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const before = await page.goto(`/projects/${uuid}/settings/access`, { waitUntil: 'domcontentloaded' });
    expect(before?.status() ?? 500).toBeLessThan(400);
    await dismissProductTour(page);

    const res = await page.request.post(`/projects/${uuid}/settings/share-links`, {
      form: {
        'project_share_create[days]': '7',
        'project_share_create[max_uses]': '2',
        'project_share_create[_token]': 'forged-csrf-token',
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    // Invalid CSRF flashes and redirects back — never a successful create landing with share-url.
    if ([302, 303, 307].includes(res.status())) {
      await page.goto(res.headers().location ?? `/projects/${uuid}/settings/access`);
    } else {
      expect(res.status()).toBeGreaterThanOrEqual(400);
      await page.goto(`/projects/${uuid}/settings/access`);
    }
    await dismissProductTour(page);
    await expect(page.locator('body')).toContainText(/csrf|security token|token de seguridad|invalid|inválid/i);
    await expect(page.locator('[data-testid="share-url"]')).toHaveCount(0);
  });

  test('email destination with non-email endpoint is rejected (UC-SEC-55)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const label = `e2e-bad-email-${Date.now().toString(36)}`;
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('email');
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill('https://example.com/not-an-email');
    const categories = form.locator('select[name="notification_destination[categories][]"]');
    if ((await categories.count()) > 0) {
      await categories.selectOption(['error'], { force: true }).catch(() => undefined);
    }
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/notifications/(new|\\S+/edit)`));
    await expect(page.locator('body')).toContainText(/does not match|no coincide|endpoint_invalid|email|invalid|inválid/i);
    await page.goto(`/projects/${uuid}/settings/alerts`);
    await dismissProductTour(page);
    await expect(page.locator('#project-notification-destinations')).not.toContainText(label);
  });

  test('oversized display name is rejected (UC-SEC-56)', async ({ page }) => {
    await page.goto('/account/profile');
    await dismissProductTour(page);
    const form = page.locator('[data-testid="profile-basic-form"]');
    await expect(form).toBeVisible({ timeout: 15_000 });
    const nameInput = form.locator('input[name="user_profile[displayName]"]');
    const previous = await nameInput.inputValue();
    await nameInput.fill('x'.repeat(121));
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/account\/profile/);
    await expect(page.locator('body')).toContainText(/too long|demasiado|longitud|120|maximum|máximo/i);

    await page.goto('/account/profile');
    await dismissProductTour(page);
    const restored = page.locator('input[name="user_profile[displayName]"]');
    const current = await restored.inputValue();
    expect(current.length, 'oversized name must not persist').toBeLessThanOrEqual(120);
    if (current !== previous && previous) {
      await restored.fill(previous);
      await page.locator('[data-testid="profile-basic-form"] button[type="submit"]').click();
      await waitForPageLoader(page);
    }
  });
});
