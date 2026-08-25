import { test, expect, type Page } from '@playwright/test';
import {
  DEMO_EMAIL,
  DEMO_PASSWORD,
  dismissCookieConsent,
  dismissProductTour,
  gotoStable,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

/**
 * Unwanted / abusive operator actions — CSRF, IDOR, confirmation mismatch,
 * open redirects, XSS, membership guards. Happy-path mutations live elsewhere.
 */
async function createEphemeralProject(page: Page, name: string): Promise<string> {
  await gotoStable(page, '/dashboard?new=1');
  await dismissProductTour(page);
  const dialog = page.locator('dialog[open], dialog.confirm-dialog[open], dialog:not([hidden])').first();
  if (!(await dialog.isVisible().catch(() => false))) {
    const trigger = page.locator('[data-tour="new-project"], [data-action="new-project"]').first();
    await trigger.click();
  }
  await expect(page.locator('input[name="project[name]"]')).toBeVisible({ timeout: 10_000 });
  await page.locator('input[name="project[name]"]').fill(name);
  await page.locator('textarea[name="project[description]"]').fill('Ephemeral Playwright unwanted-actions');
  await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();
  await page.waitForURL(/\/projects\/([0-9a-f-]{36})/i, { timeout: 30_000, waitUntil: 'domcontentloaded' });
  await dismissProductTour(page);
  const match = page.url().match(/\/projects\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse project UUID from ${page.url()}`);
  }
  return match[1];
}

async function openAddMemberForm(page: Page, projectUuid: string) {
  await page.goto(`/projects/${projectUuid}/settings/access`);
  await dismissProductTour(page);
  const members = page.locator('section.panel').filter({ hasText: /member|miembro/i }).first();
  await members.locator('button[data-action="confirm-dialog#open"]').first().click();
  const addForm = page.locator('form').filter({
    has: page.locator('#member-email, input[name="project_member_add[email]"]'),
  });
  await expect(addForm).toBeVisible({ timeout: 10_000 });
  return addForm;
}

test.describe('Unwanted actions — CSRF, IDOR, confirmations, XSS', () => {
  test('forged CSRF on issue comment is rejected (UC-SEC-13)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const marker = `e2e-csrf-comment-${Date.now().toString(36)}`;
    const commentForm = page.locator('[data-testid="issue-comments"] form, form.issue-comments__form');
    await expect(commentForm).toBeVisible({ timeout: 15_000 });
    const token = commentForm.locator('input[name="issue_comment[_token]"]');
    await expect(token).toBeAttached();
    await token.evaluate((el: HTMLInputElement) => {
      el.value = 'forged-csrf-token';
    });
    await commentForm.locator('textarea[name="issue_comment[body]"], textarea').first().fill(marker);
    await commentForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);

    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/issues/${issueUuid}`));
    await expect(page.locator('body')).toContainText(/could not add the comment|no se pudo añadir|comment_invalid|security token|csrf/i);
    await expect(page.locator('[data-testid="issue-comments"]')).not.toContainText(marker);
  });

  test('issue UUID from another project is 404 (UC-SEC-14)', async ({ page }) => {
    test.setTimeout(90_000);
    const demoUuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, demoUuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const otherUuid = await createEphemeralProject(page, `E2E IDOR ${Date.now().toString(36)}`);
    const response = await page.goto(`/projects/${otherUuid}/issues/${issueUuid}`, {
      waitUntil: 'domcontentloaded',
      timeout: 45_000,
    });
    await dismissProductTour(page);
    expect(response, 'cross-project issue response').not.toBeNull();
    expect(response!.status(), 'cross-project issue must 404').toBe(404);
    await expect(page.locator('body')).toContainText(/404|not found|no encontrad|introuvable/i);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
  });

  test('login _target_path cannot open-redirect off-site (UC-SEC-15)', async ({ browser }) => {
    test.setTimeout(90_000);
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    try {
      await page.goto('/login');
      await dismissCookieConsent(page);
      await page.locator('input[name="login_form[_username]"]').fill(DEMO_EMAIL);
      await page.locator('input[name="login_form[_password]"]').fill(DEMO_PASSWORD);
      await page.evaluate(() => {
        const form = document.querySelector('form[name="login_form"]');
        if (!(form instanceof HTMLFormElement)) {
          return;
        }
        let input = form.querySelector('input[name="_target_path"]');
        if (!(input instanceof HTMLInputElement)) {
          input = document.createElement('input');
          input.type = 'hidden';
          input.name = '_target_path';
          form.appendChild(input);
        }
        input.value = 'https://evil.example/phish';
      });
      await page
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await page.waitForURL((url) => url.hostname === 'evil.example' || !/\/login(\?|$|\/)/.test(url.pathname), {
        timeout: 45_000,
        waitUntil: 'commit',
      }).catch(() => undefined);
      await dismissProductTour(page);
      const landed = new URL(page.url());
      expect(landed.hostname, landed.href).toMatch(/localhost|127\.0\.0\.1/i);
      expect(landed.hostname, landed.href).not.toBe('evil.example');
      await expect(page.locator('body')).toBeVisible();
    } finally {
      await context.close();
    }
  });

  test('script tags in comments are stored escaped and never execute (UC-SEC-16 / UC-ISS-31)', async ({
    page,
  }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const payload = `<script>window.__e2eXss=1</script><img src=x onerror="window.__e2eXss=1"> e2e-xss-${Date.now().toString(36)}`;
    const commentForm = page.locator('[data-testid="issue-comments"] form, form.issue-comments__form');
    await expect(commentForm).toBeVisible({ timeout: 15_000 });
    await commentForm.locator('textarea[name="issue_comment[body]"], textarea').first().fill(payload);
    await commentForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);

    await expect(page.locator('[data-testid="issue-comments"]')).toContainText(payload, { timeout: 15_000 });
    const executed = await page.evaluate(() => (window as unknown as { __e2eXss?: number }).__e2eXss);
    expect(executed, 'comment HTML must not execute').toBeUndefined();
    await expect(page.locator('[data-testid="issue-comments"] script')).toHaveCount(0);
  });

  test('GET on POST-only mutations is refused (UC-SEC-17)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    for (const path of [
      `/projects/${uuid}/delete`,
      `/projects/${uuid}/clear-history`,
      `/projects/${uuid}/members`,
      `/projects/${uuid}/issues/${issueUuid}/comments`,
      `/projects/${uuid}/issues/${issueUuid}/status`,
    ]) {
      const res = await page.request.get(path, { failOnStatusCode: false, maxRedirects: 0 });
      expect(res.status(), `${path} GET must not mutate (${res.status()})`).toBeGreaterThanOrEqual(400);
      expect(res.status(), `${path} GET must not 5xx`).toBeLessThan(500);
    }
  });

  test('empty and injection-like logins stay on login (UC-SEC-18)', async ({ browser }) => {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    try {
      await page.goto('/login');
      await dismissCookieConsent(page);
      await page.locator('input[name="login_form[_username]"]').fill(`${DEMO_EMAIL}' OR '1'='1`);
      await page.locator('input[name="login_form[_password]"]').fill("' OR '1'='1");
      await page
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await expect(page).toHaveURL(/\/login/, { timeout: 20_000 });
      await expect(page).not.toHaveURL(/\/dashboard/);

      await page.goto('/login');
      await dismissCookieConsent(page);
      await page.locator('input[name="login_form[_username]"]').fill('');
      await page.locator('input[name="login_form[_password]"]').fill('');
      await page
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await expect(page).toHaveURL(/\/login/);
      await expect(page).not.toHaveURL(/\/dashboard/);
    } finally {
      await context.close();
    }
  });

  test('bogus password-reset code does not authenticate (UC-SEC-19)', async ({ browser }) => {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    try {
      await page.goto('/reset-password/complete');
      await dismissCookieConsent(page);
      const form = page.locator('form').filter({ has: page.locator('input:not([type="hidden"])') }).first();
      await expect(form).toBeVisible({ timeout: 15_000 });
      const visibleInputs = form.locator('input:not([type="hidden"]):not([type="submit"])');
      const count = await visibleInputs.count();
      for (let i = 0; i < count; i++) {
        const input = visibleInputs.nth(i);
        const type = ((await input.getAttribute('type')) ?? 'text').toLowerCase();
        if (type === 'checkbox' || type === 'radio') {
          continue;
        }
        await input.fill('00000000-not-a-real-reset-code');
      }
      await form.locator('button[type="submit"]').click();
      await page.waitForLoadState('domcontentloaded');
      await expect(page).not.toHaveURL(/\/dashboard/);
      await expect(page).toHaveURL(/\/reset-password|\/login/);
    } finally {
      await context.close();
    }
  });

  test('last owner has no remove control (UC-SEC-20)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const ownerRow = page
      .locator('section.panel li')
      .filter({ hasText: DEMO_EMAIL })
      .filter({ hasText: /owner|propietario|eigentümer|propriétaire/i })
      .first();
    await expect(ownerRow).toBeVisible({ timeout: 15_000 });
    await expect(ownerRow.locator('button[aria-label*="emove"], button[aria-label*="liminar"], form[action*="/remove"]')).toHaveCount(0);

    const forged = await page.request.post(
      `/projects/${uuid}/members/00000000-0000-4000-8000-000000000001/remove`,
      {
        form: { '_token': 'forged' },
        failOnStatusCode: false,
        maxRedirects: 0,
      },
    );
    expect(forged.status(), await forged.text()).toBeGreaterThanOrEqual(400);
    expect(forged.status()).toBeLessThan(500);
  });

  test('unknown and duplicate member emails are rejected (UC-SEC-21 / UC-SEC-22)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);

    const unknown = await openAddMemberForm(page, uuid);
    await unknown.locator('#member-email, input[name="project_member_add[email]"]').fill(
      `nobody.${Date.now().toString(36)}@example.invalid`,
    );
    await unknown.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/no user exists|no existe un usuario|must register/i);

    const duplicate = await openAddMemberForm(page, uuid);
    await duplicate.locator('#member-email, input[name="project_member_add[email]"]').fill(DEMO_EMAIL);
    await duplicate.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/already a project member|ya es miembro|already member/i);
  });

  test('wrong delete confirmation does not delete the project (UC-SEC-23)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const name = `E2E Keep ${suffix}`;
    const uuid = await createEphemeralProject(page, name);

    await page.goto(`/projects/${uuid}/settings/danger`);
    await dismissProductTour(page);
    const deleteOpen = page
      .locator('button[data-action="confirm-dialog#open"]')
      .filter({ hasText: /delete project|eliminar proyecto/i })
      .first();
    await expect(deleteOpen).toBeVisible({ timeout: 15_000 });
    await deleteOpen.click();
    const confirm = page
      .locator('dialog[open] input[name="project_delete[confirmation]"], input[name="project_delete[confirmation]"]')
      .last();
    await expect(confirm).toBeVisible({ timeout: 10_000 });
    await confirm.fill('NOT-THE-PROJECT-NAME');
    const deleteSubmit = page
      .locator('dialog[open] button[type="submit"], .confirm-dialog button[type="submit"]')
      .filter({ hasText: /delete project|eliminar proyecto/i })
      .last();
    if (await deleteSubmit.isEnabled().catch(() => false)) {
      await deleteSubmit.click();
      await waitForPageLoader(page);
    } else {
      // Client-side type-to-confirm keeps submit disabled — still a controlled unwanted action.
      await page.keyboard.press('Escape').catch(() => undefined);
    }

    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings`));
    await expect(page).not.toHaveURL(/\/dashboard(\?|$)/);
    await expect(page.locator('body')).toContainText(name);
  });

  test('guest POST cannot mutate issues (UC-SEC-24)', async ({ page, browser }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const guest = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    try {
      const res = await guest.request.post(`/projects/${uuid}/issues/${issueUuid}/comments`, {
        form: {
          'issue_comment[body]': 'guest-should-not-comment',
          'issue_comment[_token]': 'forged',
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
      expect(denied, `guest comment POST ${status} Location=${location}`).toBeTruthy();
    } finally {
      await guest.close();
    }
  });

  test('invalid JSON config import is rejected (UC-SEC-25)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/data`);
    await dismissProductTour(page);
    const panel = page.locator('[data-testid="project-config-portability"]');
    await expect(panel).toBeVisible({ timeout: 15_000 });
    await panel.locator('#tab-project-config-import').click();
    const importPane = panel.locator('[data-testid="project-config-import"]');
    await expect(importPane).toBeVisible();
    const fileInput = importPane.locator('[data-testid="project-config-file"]');
    await expect(fileInput).toBeAttached();

    await fileInput.setInputFiles({
      name: 'not-a-bundle.json',
      mimeType: 'application/json',
      buffer: Buffer.from('{"schema":"not-a-beacon-bundle","projects":"nope"}', 'utf8'),
    });
    await importPane.locator('[data-testid="project-config-import-submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('.flash-error, .flash-toast[role="alert"], [role="alert"]').first()).toBeVisible({
      timeout: 10_000,
    });
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/data`));
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).not.toContainText(/Whoops, looks like something went wrong/);
    await expect(page.locator('body')).toContainText(
      /could not import|invalid JSON|JSON o esquema|security token|token de seguridad|choose a JSON|failed|no se pudo|schema/i,
    );
    await expect(page.locator('body')).not.toContainText(/settings and memberships updated/i);
  });

  test('disabled user cannot be added as a project member (UC-SEC-26)', async ({ page }) => {
    test.setTimeout(90_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.disabled.member.${suffix}@example.invalid`;
    const password = `E2eDisMem1!${suffix}`;

    await page.goto('/admin/users?new=1');
    await dismissProductTour(page);
    const form = page.locator('form').filter({ has: page.locator('input[name="admin_user[email]"]') });
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="admin_user[email]"]').fill(email);
    await form.locator('input[name="admin_user[displayName]"]').fill(`Disabled Member ${suffix}`);
    await form.locator('input[name="admin_user[password]"]').fill(password);
    await form.locator('select[name="admin_user[role]"]').selectOption('user');
    const enabled = form.locator('input[name="admin_user[enabled]"]');
    if (await enabled.isChecked().catch(() => false)) {
      await enabled.uncheck();
    }
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);

    const uuid = await resolveDemoProjectUuid(page);
    const addForm = await openAddMemberForm(page, uuid);
    await addForm.locator('#member-email, input[name="project_member_add[email]"]').fill(email);
    await addForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/disabled|desactivad|ausgeschalt/i);
    await expect(page.locator('section.panel li').filter({ hasText: email })).toHaveCount(0);
  });

  test('authenticated locale switcher ignores off-site redirect (UC-SEC-27)', async ({ page }) => {
    await page.goto('/dashboard');
    await dismissProductTour(page);
    const switcher = page.locator('.locale-switcher, [data-locale-switcher]').first();
    await expect(switcher).toBeVisible({ timeout: 15_000 });
    await switcher.locator('summary').click();
    const localeForm = switcher.locator('form').filter({ has: page.locator('input[name*="redirect"]') }).first();
    if ((await localeForm.count()) === 0) {
      test.skip(true, 'Path-locale switcher on this surface (no POST redirect field)');
      return;
    }
    const redirect = localeForm.locator('input[name*="redirect"]').first();
    await redirect.evaluate((el: HTMLInputElement) => {
      el.value = 'https://evil.example/phish';
    });
    await localeForm.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    expect(page.url()).not.toMatch(/evil\.example/i);
    expect(new URL(page.url()).origin).toMatch(/localhost|127\.0\.0\.1/i);
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('whitespace-only comment is rejected (UC-ISS-30)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const token = await page.locator('input[name="issue_comment[_token]"]').first().inputValue();
    const before = await page.locator('[data-testid="issue-comments"] .issue-comments__item').count();
    const res = await page.request.post(`/projects/${uuid}/issues/${issueUuid}/comments`, {
      form: {
        'issue_comment[body]': '   \n\t  ',
        'issue_comment[_token]': token,
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    expect(res.status()).not.toBe(200);

    await page.goto(`/projects/${uuid}/issues/${issueUuid}`);
    await dismissProductTour(page);
    const after = await page.locator('[data-testid="issue-comments"] .issue-comments__item').count();
    expect(after, 'whitespace comment must not create a row').toBe(before);
  });

  test('share link cannot bind an issue from another project (UC-PROJ-28)', async ({ page }) => {
    const demoUuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, demoUuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const otherUuid = await createEphemeralProject(page, `E2E Share IDOR ${Date.now().toString(36)}`);
    await page.goto(`/projects/${otherUuid}/settings/access`);
    await dismissProductTour(page);
    const share = page.locator('[data-testid="share-links"]');
    await expect(share).toBeVisible({ timeout: 15_000 });
    const createForm = share.locator('form').filter({ has: page.locator('input[name="project_share_create[days]"]') });
    await createForm.locator('input[name="project_share_create[issue_uuid]"]').fill(issueUuid);
    await createForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/not found in this project|no encontrad|issue_not_found/i);
    await expect(page.locator('[data-testid="share-url"]')).toHaveCount(0);
  });
});
