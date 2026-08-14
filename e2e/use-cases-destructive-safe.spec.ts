import { test, expect } from '@playwright/test';
import {
  DEMO_EMAIL,
  DEMO_PASSWORD,
  dismissCookieConsent,
  dismissProductTour,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

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
  await page.goto('/admin/users');
  await dismissProductTour(page);
  // Prefer the dedicated new form (avoids ?new=1 dialog race).
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

  // Search so pagination / default sort cannot hide the new row.
  await page.goto(`/admin/users?q=${encodeURIComponent(email)}`);
  await dismissProductTour(page);
  const found = page.locator('table tbody tr, li, .panel').filter({ hasText: email }).first();
  if (!(await found.isVisible().catch(() => false))) {
    const errs = await page.locator('.form-error, .invalid-feedback, [class*="error"]').allTextContents();
    throw new Error(`User ${email} not listed after create. Form errors: ${errs.join(' | ') || '(none)'}`);
  }
  await expect(found).toBeVisible();
}

test.describe('Out-of-scope closing — ephemeral / deep flows', () => {
  test('remember-me cookie keeps session after session cookie cleared (UC-AUTH-08)', async ({ browser }) => {
    const ctx = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await ctx.newPage();
    await page.goto('/login');
    await dismissCookieConsent(page);

    const remember = page.locator('input[name="login_form[_remember_me]"]');
    await expect(remember).toBeVisible();
    await remember.check();
    await page.locator('input[name="login_form[_username]"]').fill(DEMO_EMAIL);
    await page.locator('input[name="login_form[_password]"]').fill(DEMO_PASSWORD);
    await page
      .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
      .first()
      .click();
    await page.waitForURL(/\/dashboard/, { timeout: 45_000 });
    await dismissProductTour(page);

    const cookies = await ctx.cookies();
    const rememberCookie = cookies.find((c) => /remember/i.test(c.name));
    expect(rememberCookie, `remember cookie among ${cookies.map((c) => c.name).join(',')}`).toBeTruthy();

    const sessionCookies = cookies.filter((c) => /sess|PHPSESSID|SYMFONY/i.test(c.name) && !/remember/i.test(c.name));
    for (const c of sessionCookies) {
      await ctx.clearCookies({ name: c.name, domain: c.domain, path: c.path });
    }

    await page.goto('/dashboard');
    await dismissProductTour(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toBeVisible();
    await ctx.close();
  });

  test('ephemeral project: clear history then delete (UC-PROJ-17 / UC-PROJ-19)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const name = `E2E Danger ${suffix}`;
    const uuid = await createEphemeralProject(page, name);

    await page.goto(`/projects/${uuid}/settings/danger`);
    await dismissProductTour(page);
    await expect(page.locator('form[action*="clear-history"], button:has-text("Clear history"), button:has-text("Vaciar historial")').first()).toBeVisible();

    const clearOpen = page
      .locator('button[data-action="confirm-dialog#open"]')
      .filter({ hasText: /clear history|vaciar historial/i })
      .first();
    await clearOpen.click();
    const clearSubmit = page
      .locator('dialog[open] button[type="submit"], .confirm-dialog button[type="submit"]')
      .filter({ hasText: /clear history|vaciar historial|yes/i })
      .last();
    await expect(clearSubmit).toBeVisible({ timeout: 10_000 });
    await clearSubmit.click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(/cleared|vaciado|historial/i);

    // Delete requires typing the exact project name.
    const deleteOpen = page
      .locator('button[data-action="confirm-dialog#open"]')
      .filter({ hasText: /delete project|eliminar proyecto/i })
      .first();
    await deleteOpen.click();
    const confirm = page.locator('dialog[open] input[name="project_delete[confirmation]"], input[name="project_delete[confirmation]"]').last();
    await expect(confirm).toBeVisible({ timeout: 10_000 });
    await confirm.fill(name);
    const deleteSubmit = page
      .locator('dialog[open] button[type="submit"], .confirm-dialog button[type="submit"]')
      .filter({ hasText: /delete project|eliminar proyecto/i })
      .last();
    await expect(deleteSubmit).toBeEnabled({ timeout: 5_000 });
    await deleteSubmit.click();
    await page.waitForURL(/\/dashboard/, { timeout: 30_000 });
    await expect(page.locator('body')).toContainText(/deleted|eliminado/i);
  });

  test('transfer ownership on ephemeral project with second member (UC-PROJ-18)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const projectName = `E2E Transfer ${suffix}`;
    const email = `e2e.xfer.${suffix}@example.invalid`;
    const password = `E2eXfer!${suffix}Aa1`;

    await createEnabledUser(page, email, password, `Xfer ${suffix}`);
    const uuid = await createEphemeralProject(page, projectName);

    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const members = page.locator('section.panel').filter({ hasText: /member|miembro/i }).first();
    await members.locator('button[data-action="confirm-dialog#open"]').first().click();
    const addForm = page.locator('form').filter({
      has: page.locator('#member-email, input[name="project_member_add[email]"]'),
    });
    await addForm.locator('#member-email, input[name="project_member_add[email]"]').fill(email);
    const roleSelect = addForm.locator('#member-add-role, select[name="project_member_add[role]"]');
    if ((await roleSelect.locator('option[value="admin"]').count()) > 0) {
      await roleSelect.selectOption('admin');
    } else if ((await roleSelect.locator('option[value="member"]').count()) > 0) {
      await roleSelect.selectOption('member');
    }
    await addForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);

    await page.goto(`/projects/${uuid}/settings/danger`);
    await dismissProductTour(page);
    const transferOpen = page
      .locator('button[data-action="confirm-dialog#open"]')
      .filter({ hasText: /transfer|ceder|propiedad|ownership/i })
      .first();
    if (!(await transferOpen.isVisible().catch(() => false))) {
      requireSampleOrSkip(false, 'Transfer ownership control missing');
      return;
    }
    await transferOpen.click();
    const userSelect = page.locator('dialog[open] select[name="project_transfer_ownership[user]"]').last();
    await expect(userSelect).toBeVisible({ timeout: 10_000 });
    const opt = userSelect.locator('option').filter({ hasText: email }).first();
    if ((await opt.count()) === 0) {
      // Options may be display names — pick first non-empty.
      await userSelect.selectOption({ index: 1 });
    } else {
      await userSelect.selectOption({ label: await opt.innerText() });
    }
    await page.locator('dialog[open] input[name="project_transfer_ownership[confirmation]"]').fill(projectName);
    await page
      .locator('dialog[open] button[type="submit"]')
      .filter({ hasText: /transfer|ceder/i })
      .last()
      .click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('admin anonymizes ephemeral user (UC-ADM-04)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const email = `e2e.anon.${suffix}@example.invalid`;
    await createEnabledUser(page, email, `E2eAnon!${suffix}Aa1`, `Anon ${suffix}`);

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
    const input = dialog.locator('[data-confirm-dialog-target="confirmInput"], input[id^="admin-user-anonymize-input"]').first();
    await input.click();
    await input.fill('');
    await input.pressSequentially('ANONYMIZE', { delay: 20 });
    const submit = dialog.locator('button[data-confirm-dialog-target="submit"]');
    await expect(submit).toBeEnabled({ timeout: 10_000 });
    await submit.click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(/anonymized|anonimiz/i);
  });

  test('mark duplicate with merge_events when candidates exist (UC-ISS-18)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const panel = page.locator('[data-testid="mark-duplicate"]');
    await expect(panel).toBeAttached();
    const open = panel.locator('button[data-action="confirm-dialog#open"]').first();
    if (!(await open.isVisible().catch(() => false)) || (await open.isDisabled().catch(() => false))) {
      requireSampleOrSkip(false, 'No duplicate candidates on first issue');
      return;
    }
    await open.click();
    const dialog = page.locator('dialog[open], .confirm-dialog[open]').last();
    await expect(dialog).toBeVisible({ timeout: 10_000 });
    const merge = dialog.locator('input[name="issue_duplicate[merge_events]"]');
    if ((await merge.count()) > 0 && !(await merge.isChecked().catch(() => false))) {
      await merge.check();
    }
    const option = dialog.locator('#issue-duplicate-options button.combobox__option, button.combobox__option').first();
    if ((await option.count()) === 0) {
      const query = dialog.locator('input[name="issue_duplicate[query]"]');
      if ((await query.count()) > 0) {
        await query.fill('a');
        await page.waitForTimeout(600);
      }
    }
    const opt2 = dialog.locator('button.combobox__option').first();
    if ((await opt2.count()) === 0) {
      await page.keyboard.press('Escape').catch(() => undefined);
      requireSampleOrSkip(false, 'No combobox options for duplicate');
      return;
    }
    await opt2.click();
    await dialog.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(/duplicate|duplicad|merged|fusionad|ignored|ignorad/i);
  });

  test('send test notification queues flash for HTTP destination (UC-NOTIF-04)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible();
    const label = `e2e-testsend-${Date.now().toString(36)}`;
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('http');
    // OutboundUrlGuard accepts example.com (example.invalid is rejected as invalid endpoint).
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill('https://example.com/hooks/beacon-test');
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
    const testBtn = row.getByRole('button', { name: /send test|enviar prueba|test/i }).first();
    await expect(testBtn).toBeVisible({ timeout: 10_000 });
    await testBtn.click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(/queued|encolad|test|prueba|sample|muestra/i);

    const del = row.locator('form[action*="/delete"] button[type="submit"]').first();
    if (await del.isVisible().catch(() => false)) {
      page.once('dialog', (d) => d.accept().catch(() => undefined));
      await del.click({ force: true });
      await waitForPageLoader(page);
    }
  });
});
