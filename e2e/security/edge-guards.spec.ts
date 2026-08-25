import { test, expect, type Page } from '@playwright/test';
import {
  DEMO_EMAIL,
  dismissCookieConsent,
  dismissProductTour,
  gotoStable,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';
import { createEnabledUser } from '../support/security';

/**
 * Extra edge / unwanted-action guards beyond UC-SEC-13..27.
 * Confirmations, CSRF on danger zone, triage validation, token revoke, XSS chrome.
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
  await page.locator('textarea[name="project[description]"]').fill('Ephemeral Playwright edge-guards');
  await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();
  await page.waitForURL(/\/projects\/([0-9a-f-]{36})/i, { timeout: 30_000 });
  await dismissProductTour(page);
  const match = page.url().match(/\/projects\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse project UUID from ${page.url()}`);
  }
  return match[1];
}

async function addMember(page: Page, projectUuid: string, email: string, role = 'admin'): Promise<void> {
  await page.goto(`/projects/${projectUuid}/settings/access`);
  await dismissProductTour(page);
  const members = page.locator('section.panel').filter({ hasText: /member|miembro/i }).first();
  await members.locator('button[data-action="confirm-dialog#open"]').first().click();
  const addForm = page.locator('form').filter({
    has: page.locator('#member-email, input[name="project_member_add[email]"]'),
  });
  await expect(addForm).toBeVisible({ timeout: 10_000 });
  await addForm.locator('#member-email, input[name="project_member_add[email]"]').fill(email);
  const roleSelect = addForm.locator('#member-add-role, select[name="project_member_add[role]"]');
  if ((await roleSelect.locator(`option[value="${role}"]`).count()) > 0) {
    await roleSelect.selectOption(role);
  }
  await addForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
  await waitForPageLoader(page);
  await expect(page.getByRole('main')).toContainText(email, { timeout: 15_000 });
}

test.describe('Edge guards — confirmations, triage abuse, revoke, XSS', () => {
  test('wrong transfer confirmation does not transfer ownership (UC-SEC-28)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const projectName = `E2E KeepOwner ${suffix}`;
    const email = `e2e.edge.xfer.${suffix}@example.invalid`;
    const password = `E2eEdgeXfer1!${suffix}`;

    await createEnabledUser(page, email, password, `Edge Xfer ${suffix}`);
    const uuid = await createEphemeralProject(page, projectName);
    await addMember(page, uuid, email, 'admin');

    await page.goto(`/projects/${uuid}/settings/danger`);
    await dismissProductTour(page);
    const transferOpen = page
      .locator('button[data-action="confirm-dialog#open"]')
      .filter({ hasText: /transfer|ceder|propiedad|ownership/i })
      .first();
    await expect(transferOpen).toBeVisible({ timeout: 15_000 });
    await transferOpen.click();

    const dialog = page.locator('dialog[open], .confirm-dialog[open]').last();
    const userSelect = dialog.locator('select[name="project_transfer_ownership[user]"]');
    await expect(userSelect).toBeVisible({ timeout: 10_000 });
    const opt = userSelect.locator('option').filter({ hasText: email }).first();
    if ((await opt.count()) > 0) {
      await userSelect.selectOption({ label: await opt.innerText() });
    } else {
      await userSelect.selectOption({ index: 1 });
    }
    await dialog.locator('input[name="project_transfer_ownership[confirmation]"]').fill('NOT-THE-PROJECT-NAME');
    const submit = dialog.locator('button[type="submit"]').filter({ hasText: /transfer|ceder/i }).last();
    if (await submit.isEnabled().catch(() => false)) {
      await submit.click();
      await waitForPageLoader(page);
      await expect(page.locator('body')).toContainText(/did not match|no coincid|ownership was not|no se transf/i);
    } else {
      await page.keyboard.press('Escape').catch(() => undefined);
    }

    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const ownerRow = page
      .locator('section.panel li')
      .filter({ hasText: DEMO_EMAIL })
      .filter({ hasText: /owner|propietario|eigentümer|propriétaire/i })
      .first();
    await expect(ownerRow).toBeVisible({ timeout: 15_000 });
  });

  test('forged CSRF on project delete is rejected (UC-SEC-29)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const name = `E2E CsrfDel ${suffix}`;
    const uuid = await createEphemeralProject(page, name);

    const res = await page.request.post(`/projects/${uuid}/delete`, {
      form: {
        'project_delete[confirmation]': name,
        'project_delete[_token]': 'forged-csrf-token',
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    expect([302, 303, 400, 403, 422]).toContain(res.status());

    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings`));
    await expect(page.locator('body')).toContainText(name);
  });

  test('forged CSRF on clear-history is rejected (UC-SEC-30)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const res = await page.request.post(`/projects/${uuid}/clear-history`, {
      form: {
        'project_clear_history[confirm]': '1',
        'project_clear_history[_token]': 'forged-csrf-token',
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    // Invalid CSRF must not clear (AccessDenied → 403; never 2xx).
    expect(res.status()).toBeGreaterThanOrEqual(400);

    const after = await page.goto(`/projects/${uuid}/issues/${issueUuid}`, { waitUntil: 'domcontentloaded' });
    await dismissProductTour(page);
    expect(after, 'issue must still exist after forged clear-history').not.toBeNull();
    expect(after!.status()).toBeLessThan(400);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/issues/${issueUuid}`));
  });

  test('assigning a non-member user id is rejected (UC-SEC-31)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    // Priority form also uses class issue-assignee-form — target the real assignee form.
    const form = page.locator('form.issue-assignee-form').filter({
      has: page.locator('input[name="issue_assignee[_token]"]'),
    });
    await expect(form).toBeVisible({ timeout: 15_000 });
    const token = await form.locator('input[name="issue_assignee[_token]"]').inputValue();
    const res = await page.request.post(`/projects/${uuid}/issues/${issueUuid}/assign`, {
      form: {
        'issue_assignee[assignee]': '999999999',
        'issue_assignee[_token]': token,
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    expect([302, 303, 400, 422]).toContain(res.status());

    await gotoStable(page, `/projects/${uuid}/issues/${issueUuid}`);
    await dismissProductTour(page);
    await expect(page.locator('body')).toContainText(
      /not a member|no es miembro|could not update the assignee|no se pudo actualizar la asignación|assignee_invalid|invalid/i,
    );
  });

  test('invalid priority value is rejected (UC-SEC-32)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const form = page.locator('form.issue-priority-form, .issue-priority-actions form').first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    const token = await form.locator('input[name="issue_priority[_token]"]').inputValue();
    const previous = await form.locator('select[name="issue_priority[priority]"]').inputValue().catch(() => '');

    const res = await page.request.post(`/projects/${uuid}/issues/${issueUuid}/priority`, {
      form: {
        'issue_priority[priority]': 'not-a-real-priority',
        'issue_priority[_token]': token,
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    expect([302, 303, 400, 422]).toContain(res.status());

    await gotoStable(page, `/projects/${uuid}/issues/${issueUuid}`);
    await dismissProductTour(page);
    await expect(page.locator('body')).toContainText(/could not update the priority|no se pudo|priority_invalid|invalid/i);
    if (previous) {
      await expect(page.locator('select[name="issue_priority[priority]"]')).toHaveValue(previous);
    }
  });

  test('oversized comment is rejected (UC-SEC-33 / UC-ISS-32)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const before = await page.locator('[data-testid="issue-comments"] .issue-comments__item').count();
    const token = await page.locator('input[name="issue_comment[_token]"]').first().inputValue();
    // Form Length(max: 5000) rejects before the service — flash is comment_invalid.
    const body = 'x'.repeat(5001);
    const res = await page.request.post(`/projects/${uuid}/issues/${issueUuid}/comments`, {
      form: {
        'issue_comment[body]': body,
        'issue_comment[_token]': token,
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    expect(res.status()).not.toBe(200);

    await gotoStable(page, `/projects/${uuid}/issues/${issueUuid}`);
    await dismissProductTour(page);
    await expect(page.locator('.flash-error, .flash-toast[role="alert"], [role="alert"]').first()).toBeVisible({
      timeout: 10_000,
    });
    await expect(page.locator('body')).toContainText(
      /too long|demasiado largo|could not add the comment|no se pudo añadir el comentario|comment_too_long|comment_invalid/i,
    );
    const after = await page.locator('[data-testid="issue-comments"] .issue-comments__item').count();
    expect(after, 'oversized comment must not create a row').toBe(before);
  });

  test('mark duplicate of self or foreign UUID is rejected (UC-SEC-34)', async ({ page }) => {
    test.setTimeout(90_000);
    const demoUuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, demoUuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    async function duplicateToken(): Promise<string> {
      const open = page.locator('[data-testid="mark-duplicate"] button[data-action="confirm-dialog#open"]').first();
      if (await open.isVisible().catch(() => false)) {
        await open.click().catch(() => undefined);
      }
      const tokenInput = page.locator('input[name="issue_duplicate[_token]"]').first();
      await expect(tokenInput).toBeAttached({ timeout: 10_000 });
      return tokenInput.inputValue();
    }

    const selfToken = await duplicateToken();
    const selfRes = await page.request.post(`/projects/${demoUuid}/issues/${issueUuid}/duplicate`, {
      form: {
        'issue_duplicate[canonical_uuid]': issueUuid,
        'issue_duplicate[merge_events]': '0',
        'issue_duplicate[_token]': selfToken,
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(selfRes.status(), await selfRes.text()).toBeLessThan(500);

    await gotoStable(page, `/projects/${demoUuid}/issues/${issueUuid}`);
    await dismissProductTour(page);
    await expect(page.locator('.flash-error, .flash-toast[role="alert"], [role="alert"]').first()).toContainText(
      /cannot be a duplicate of itself|no puede ser duplicado de sí|duplicate_self|could not mark|no se pudo marcar/i,
    );

    const foreignToken = await duplicateToken();
    const foreign = '00000000-0000-4000-8000-000000000099';
    const foreignRes = await page.request.post(`/projects/${demoUuid}/issues/${issueUuid}/duplicate`, {
      form: {
        'issue_duplicate[canonical_uuid]': foreign,
        'issue_duplicate[merge_events]': '0',
        'issue_duplicate[_token]': foreignToken,
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(foreignRes.status(), await foreignRes.text()).toBeLessThan(500);

    await gotoStable(page, `/projects/${demoUuid}/issues/${issueUuid}`);
    await dismissProductTour(page);
    await expect(page.locator('.flash-error, .flash-toast[role="alert"], [role="alert"]').first()).toContainText(
      /not found in this project|no se encontr|canonical|duplicate_not_found|could not mark|no se pudo marcar/i,
    );
  });

  test('wrong anonymize confirmation does not anonymize the user (UC-SEC-35)', async ({ page }) => {
    test.setTimeout(90_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.edge.anon.${suffix}@example.invalid`;
    await createEnabledUser(page, email, `E2eEdgeAnon1!${suffix}`, `Edge Anon ${suffix}`);

    await page.goto(`/admin/users?q=${encodeURIComponent(email)}`);
    await waitForPageLoader(page);
    const row = page.locator('tr').filter({ hasText: email }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    const trigger = row
      .locator('button[data-action="confirm-dialog#open"]')
      .filter({ hasText: /anonymize|anonimiz/i })
      .first();
    await expect(trigger).toBeVisible({ timeout: 10_000 });
    await trigger.click();

    const dialog = page.locator('dialog[open], .confirm-dialog[open]').last();
    await expect(dialog).toBeVisible({ timeout: 10_000 });
    const input = dialog
      .locator('[data-confirm-dialog-target="confirmInput"], input[id^="admin-user-anonymize-input"]')
      .first();
    await input.fill('WRONG');
    const submit = dialog.locator('button[data-confirm-dialog-target="submit"], button[type="submit"]').last();
    if (await submit.isEnabled().catch(() => false)) {
      await submit.click();
      await waitForPageLoader(page);
    } else {
      await page.keyboard.press('Escape').catch(() => undefined);
    }

    await page.goto(`/admin/users?q=${encodeURIComponent(email)}`);
    await waitForPageLoader(page);
    await expect(page.locator('tr').filter({ hasText: email })).toBeVisible();
    await expect(page.locator('tr').filter({ hasText: email })).not.toContainText(/anonymized|anonimizad/i);
  });

  test('guest POST cannot delete a project (UC-SEC-36)', async ({ page, browser }) => {
    const suffix = Date.now().toString(36);
    const name = `E2E GuestDel ${suffix}`;
    const uuid = await createEphemeralProject(page, name);

    const guest = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    try {
      const res = await guest.request.post(`/projects/${uuid}/delete`, {
        form: {
          'project_delete[confirmation]': name,
          'project_delete[_token]': 'forged',
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
      expect(denied, `guest delete POST ${status} Location=${location}`).toBeTruthy();
    } finally {
      await guest.close();
    }

    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    await expect(page.locator('body')).toContainText(name);
  });

  test('javascript: destination URL is rejected (UC-SEC-37 / UC-NOTIF-18)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const label = `e2e-js-url-${Date.now().toString(36)}`;
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('http');
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill('javascript:alert(1)');
    const categories = form.locator('select[name="notification_destination[categories][]"]');
    if ((await categories.count()) > 0) {
      await categories.selectOption(['error'], { force: true }).catch(() => undefined);
    }
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    // Must stay on the create form (not land on alerts with a saved destination).
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/notifications/(new|\\S+/edit)`));
    await expect(page.locator('body')).toContainText(
      /does not match the selected type|no coincide con el tipo|endpoint_invalid|private|ssrf|not allowed|no permitido/i,
    );
    await page.goto(`/projects/${uuid}/settings/alerts`);
    await dismissProductTour(page);
    await expect(page.locator('#project-notification-destinations')).not.toContainText(label);
  });

  test('revoked Read API token cannot list issues (UC-ING-23)', async ({ page, request }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const tokens = page.locator('[data-testid="read-api-tokens"]');
    await expect(tokens).toBeVisible({ timeout: 15_000 });
    const label = `e2e-revoke-${Date.now().toString(36)}`;
    await tokens.locator('input[name="project_read_token_create[label]"]').fill(label);
    await tokens.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    const secret = page.locator('[data-testid="read-token-secret"]');
    await expect(secret).toBeVisible({ timeout: 15_000 });
    const bearer = (await secret.innerText()).trim();

    const ok = await request.get(`/api/projects/${uuid}/issues`, {
      headers: { Authorization: `Bearer ${bearer}` },
      failOnStatusCode: false,
    });
    expect(ok.status(), await ok.text()).toBe(200);

    const row = page.locator('[data-testid="read-token-row"]').filter({ hasText: label }).first();
    await expect(row).toBeVisible({ timeout: 10_000 });
    const revoke = row.locator('form[action*="/revoke"] button[type="submit"]').first();
    await revoke.click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/revoked|revocad/i);

    const denied = await request.get(`/api/projects/${uuid}/issues`, {
      headers: { Authorization: `Bearer ${bearer}` },
      failOnStatusCode: false,
    });
    expect(denied.status(), await denied.text()).toBe(401);
    await expect(await denied.text()).toMatch(/unauthorized/i);
  });

  test('script-like project name is stored escaped on overview (UC-SEC-38)', async ({ page }) => {
    const payload = `<img src=x onerror="window.__e2eProjXss=1"> e2e-name-${Date.now().toString(36)}`;
    const uuid = await createEphemeralProject(page, payload);

    await page.goto(`/projects/${uuid}`);
    await dismissProductTour(page);
    await expect(page.locator('h1, [data-testid="project-name"]').first()).toContainText(payload);
    const executed = await page.evaluate(() => (window as unknown as { __e2eProjXss?: number }).__e2eProjXss);
    expect(executed, 'project name HTML must not execute').toBeUndefined();
    await expect(page.locator('h1 script, h1 img[onerror]')).toHaveCount(0);
  });

  test('magic-login with unknown email stays public (UC-SEC-39)', async ({ browser }) => {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    try {
      await page.goto('/login/magic');
      await dismissCookieConsent(page);
      const form = page.locator('form').filter({ has: page.locator('input[type="email"], input[name*="email"]') }).first();
      if ((await form.count()) === 0) {
        test.skip(true, 'Magic login form not available on this install');
        return;
      }
      await expect(form).toBeVisible({ timeout: 15_000 });
      const email = form.locator('input[type="email"], input[name*="email"]').first();
      await email.fill(`nobody.${Date.now().toString(36)}@example.invalid`);
      await form.locator('button[type="submit"]').click();
      await page.waitForLoadState('domcontentloaded');
      await expect(page).not.toHaveURL(/\/dashboard/);
      await expect(page).toHaveURL(/\/login|\/magic/);
      await expect(page.locator('body')).not.toContainText(/Whoops, looks like something went wrong/);
    } finally {
      await context.close();
    }
  });
});
