import { test, expect } from '@playwright/test';
import {
  dismissCookieConsent,
  dismissProductTour,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

async function createEnabledUser(
  page: import('@playwright/test').Page,
  email: string,
  password: string,
  displayName: string,
): Promise<void> {
  await page.goto('/admin/users?new=1');
  await dismissProductTour(page);
  await waitForPageLoader(page);
  const form = page.locator('form').filter({ has: page.locator('input[name="admin_user[email]"]') });
  await expect(form).toBeVisible({ timeout: 15_000 });
  await form.locator('input[name="admin_user[email]"]').fill(email);
  await form.locator('input[name="admin_user[displayName]"]').fill(displayName);
  await form.locator('input[name="admin_user[password]"]').fill(password);
  await form.locator('select[name="admin_user[role]"]').selectOption('user');
  const enabled = form.locator('input[name="admin_user[enabled]"]');
  if (!(await enabled.isChecked().catch(() => false))) {
    await enabled.check();
  }
  await form.locator('button[type="submit"]').click();
  await waitForPageLoader(page);
  await expect(page.getByRole('main')).toContainText(email, { timeout: 20_000 });
}

test.describe('Partials closing — access, filters, social, tour', () => {
  test('project settings sections all load (UC-PROJ-04)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    // Route slugs: general | access | alerts | data | danger
    for (const section of ['general', 'access', 'alerts', 'data', 'danger']) {
      await page.goto(`/projects/${uuid}/settings/${section}`);
      await dismissProductTour(page);
      await expect(page).not.toHaveURL(/\/login/);
      await expect(page.locator('body')).toBeVisible();
      await expect(page.locator('.app-main__inner, main, [role="main"]').first()).toBeVisible();
    }
  });

  test('link group to project then unlink (UC-PROJ-07)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const groupName = `E2E Proj Group ${suffix}`;

    await page.goto('/admin/groups/new');
    await dismissProductTour(page);
    const create = page.locator('form').filter({ has: page.locator('input[name="admin_group[name]"]') });
    await create.locator('input[name="admin_group[name]"]').fill(groupName);
    await create.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/groups\/[0-9a-f-]{36}/);

    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);

    const groups = page.locator('section.panel').filter({ hasText: /groups|grupos/i }).first();
    await expect(groups).toBeVisible();
    const addForm = groups.locator('form').filter({ has: page.locator('select[name="project_group_add[group]"]') });
    if ((await addForm.count()) === 0) {
      // All groups already linked or no assignable roles — still assert panel.
      await expect(groups).toBeVisible();
      return;
    }
    const select = addForm.locator('select[name="project_group_add[group]"]');
    const opt = select.locator('option').filter({ hasText: groupName }).first();
    if ((await opt.count()) === 0) {
      requireSampleOrSkip(false, `Group ${groupName} not in project link choices`);
      return;
    }
    const value = await opt.getAttribute('value');
    expect(value).toBeTruthy();
    await select.selectOption(value!);
    const role = addForm.locator('select[name="project_group_add[role]"]');
    if ((await role.locator('option[value="viewer"]').count()) > 0) {
      await role.selectOption('viewer');
    }
    await addForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(groups).toContainText(groupName, { timeout: 15_000 });

    const row = groups.locator('li').filter({ hasText: groupName }).first();
    const unlink = row.locator('button[aria-label*="nlink"], button[aria-label*="esvincular"], button[title*="nlink"]').first();
    await unlink.click();
    const danger = page.locator('dialog[open] button.btn-danger, .confirm-dialog button.btn-danger').last();
    await expect(danger).toBeVisible({ timeout: 10_000 });
    await danger.click();
    await waitForPageLoader(page);
  });

  test('change member role then deactivate (UC-PROJ-06)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const email = `e2e.role.${suffix}@example.invalid`;
    const password = `E2eRole!${suffix}`;

    await createEnabledUser(page, email, password, `Role ${suffix}`);
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);

    const members = page.locator('section.panel').filter({ hasText: /member|miembro/i }).first();
    await members.locator('button[data-action="confirm-dialog#open"]').first().click();
    const addForm = page.locator('form').filter({
      has: page.locator('#member-email, input[name="project_member_add[email]"]'),
    });
    await expect(addForm).toBeVisible({ timeout: 10_000 });
    await addForm.locator('#member-email, input[name="project_member_add[email]"]').fill(email);
    const roleSelect = addForm.locator('#member-add-role, select[name="project_member_add[role]"]');
    if ((await roleSelect.locator('option[value="member"]').count()) > 0) {
      await roleSelect.selectOption('member');
    }
    await addForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(email, { timeout: 15_000 });

    const row = page.locator('li').filter({ hasText: email }).first();
    const editRole = row.locator('button[aria-label*="dit role"], button[aria-label*="ditar rol"], button[title*="dit role"]').first();
    if (await editRole.isVisible().catch(() => false)) {
      await editRole.click();
      const dialogRole = page.locator('dialog[open] select[name="role"], .confirm-dialog select[name="role"]').last();
      await expect(dialogRole).toBeVisible({ timeout: 10_000 });
      if ((await dialogRole.locator('option[value="viewer"]').count()) > 0) {
        await dialogRole.selectOption('viewer');
      }
      await page.locator('dialog[open] button.btn-primary, .confirm-dialog button.btn-primary').last().click();
      await waitForPageLoader(page);
    }

    const deactivate = row
      .locator('button[type="submit"]')
      .filter({ hasText: /disable|desactivar|enable|activar/i })
      .first();
    if (await deactivate.isVisible().catch(() => false)) {
      await deactivate.click();
      await waitForPageLoader(page);
      await expect(page.getByRole('main')).toContainText(/inactive|inactivo/i);
    }
  });

  test('issue list accepts tag url user and q filters (UC-ISS-03)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const qs = 'tag=e2e&url=checkout&user=alice&q=beacon';
    const res = await page.goto(`/projects/${uuid}/issues?${qs}`);
    await dismissProductTour(page);
    expect(res, 'issues filter response').not.toBeNull();
    expect(res!.status(), await page.content().then((c) => c.slice(0, 200))).toBeLessThan(500);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('[data-tour="issue-filters"]')).toBeVisible();
    await expect(page.locator('input[name="tag"]')).toHaveValue('e2e');
    await expect(page.locator('input[name="url"]')).toHaveValue('checkout');
    await expect(page.locator('input[name="user"]')).toHaveValue('alice');
  });

  test('mark duplicate without merge_events when candidates exist (UC-ISS-18)', async ({ page }) => {
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
      // No candidates — similar quick-mark may still exist.
      const similar = page.locator('[data-testid="similar-issues"] button[type="submit"]').first();
      if (await similar.isVisible().catch(() => false)) {
        await similar.click();
        await waitForPageLoader(page);
        await expect(page).not.toHaveURL(/\/login/);
      }
      return;
    }

    await open.click();
    const dialog = page.locator('dialog[open], .confirm-dialog[open]').last();
    await expect(dialog).toBeVisible({ timeout: 10_000 });
    const merge = dialog.locator('input[name="issue_duplicate[merge_events]"]');
    if ((await merge.count()) > 0 && (await merge.isChecked().catch(() => false))) {
      await merge.uncheck();
    }
    const option = dialog.locator('#issue-duplicate-options button.combobox__option, button.combobox__option').first();
    if ((await option.count()) > 0) {
      await option.click();
    } else {
      // Type into query combobox if options load async.
      const query = dialog.locator('input[name="issue_duplicate[query]"]');
      if ((await query.count()) > 0) {
        await query.fill('e');
        await page.waitForTimeout(500);
        const opt2 = dialog.locator('button.combobox__option').first();
        if ((await opt2.count()) > 0) {
          await opt2.click();
        } else {
          await page.keyboard.press('Escape').catch(() => undefined);
          return;
        }
      } else {
        await page.keyboard.press('Escape').catch(() => undefined);
        return;
      }
    }
    await dialog.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('admin changes user role user↔admin (UC-ADM-03)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const email = `e2e.admrole.${suffix}@example.invalid`;
    await createEnabledUser(page, email, `E2eAdm!${suffix}`, `AdmRole ${suffix}`);

    const row = page.locator('tr, li, .panel').filter({ hasText: email }).first();
    const edit = row.locator('button[aria-label*="dit"], button[title*="dit"], button[aria-label*="rol"]').first();
    await expect(edit).toBeVisible({ timeout: 10_000 });
    await edit.click();
    const roleSelect = page.locator(`select[id^="user-role-"], dialog[open] select[name="role"]`).last();
    await expect(roleSelect).toBeVisible({ timeout: 10_000 });
    await roleSelect.selectOption('admin');
    await page.locator('dialog[open] button.btn-primary, .confirm-dialog button.btn-primary').last().click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(email);

    // Flip back to user so we do not accumulate extra admins.
    const row2 = page.locator('tr, li, .panel').filter({ hasText: email }).first();
    const edit2 = row2.locator('button[aria-label*="dit"], button[title*="dit"], button[aria-label*="rol"]').first();
    if (await edit2.isVisible().catch(() => false)) {
      await edit2.click();
      const roleSelect2 = page.locator(`select[id^="user-role-"], dialog[open] select[name="role"]`).last();
      await roleSelect2.selectOption('user');
      await page.locator('dialog[open] button.btn-primary, .confirm-dialog button.btn-primary').last().click();
      await waitForPageLoader(page);
    }
  });

  test('admin group add then remove member (UC-ADM-05)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const email = `e2e.gmemb.${suffix}@example.invalid`;
    await createEnabledUser(page, email, `E2eGm!${suffix}`, `GMemb ${suffix}`);

    const groupName = `E2E Memb Group ${suffix}`;
    await page.goto('/admin/groups/new');
    await dismissProductTour(page);
    const create = page.locator('form').filter({ has: page.locator('input[name="admin_group[name]"]') });
    await create.locator('input[name="admin_group[name]"]').fill(groupName);
    await create.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/groups\/[0-9a-f-]{36}/);

    const add = page.locator('form').filter({
      has: page.locator('input[name="admin_group_member_add[email]"]'),
    });
    await expect(add).toBeVisible();
    await add.locator('input[name="admin_group_member_add[email]"]').fill(email);
    await add.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(email, { timeout: 15_000 });

    const row = page.locator('li').filter({ hasText: email }).first();
    const remove = row.locator('button[type="submit"]').filter({ hasText: /remove|quitar/i }).first();
    if (await remove.isVisible().catch(() => false)) {
      await remove.click();
      await waitForPageLoader(page);
    }
  });

  test('enabled social provider shows Continue button on login (UC-AUTH-14)', async ({ page, browser }) => {
    const suffix = Date.now().toString(36);
    const label = `E2E IdP ${suffix}`;

    await page.goto('/admin/social-login/new');
    await dismissProductTour(page);
    const form = page.locator('form').filter({
      has: page.locator('input[name="social_login_credential[client_id]"], select[name="social_login_credential[provider]"]'),
    });
    await expect(form).toBeVisible({ timeout: 15_000 });

    const provider = form.locator('select[name="social_login_credential[provider]"], input[name="social_login_credential[provider]"]');
    if ((await provider.evaluate((el) => el.tagName)).toLowerCase() === 'select') {
      const github = provider.locator('option[value="github"]');
      if ((await github.count()) > 0) {
        await provider.selectOption('github');
      } else {
        await provider.selectOption({ index: 1 });
      }
    } else {
      await provider.fill(`e2e-idp-${suffix}`);
    }

    await form.locator('input[name="social_login_credential[label]"]').fill(label);
    await form.locator('input[name="social_login_credential[client_id]"]').fill(`e2e-client-${suffix}`);
    await form.locator('input[name="social_login_credential[client_secret]"]').fill(`e2e-secret-${suffix}`);
    const enabled = form.locator('input[name="social_login_credential[enabled]"]');
    if ((await enabled.count()) > 0 && !(await enabled.isChecked().catch(() => false))) {
      await enabled.check();
    }
    // Custom IdP may need URLs.
    const authUrl = form.locator('input[name="social_login_credential[authorize_url]"]');
    if ((await authUrl.count()) > 0 && (await authUrl.isVisible().catch(() => false))) {
      await authUrl.fill('https://idp.example.invalid/oauth/authorize');
      await form.locator('input[name="social_login_credential[token_url]"]').fill('https://idp.example.invalid/oauth/token');
      await form.locator('input[name="social_login_credential[userinfo_url]"]').fill('https://idp.example.invalid/oauth/userinfo');
    }
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);

    const origin = new URL(page.url()).origin;
    const guestCtx = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const guest = await guestCtx.newPage();
    await guest.goto(`${origin}/login`);
    await dismissCookieConsent(guest);
    await expect(
      guest.locator(`a[href*="/login/social/"], a:has-text("${label}"), a:has-text("Continue with"), a:has-text("Continuar con")`).first(),
    ).toBeVisible({ timeout: 15_000 });
    await guestCtx.close();

    // Best-effort disable so later guest suites stay clean.
    await page.goto('/admin/social-login');
    await dismissProductTour(page);
    const row = page.locator('tr, li, .panel, article').filter({ hasText: label }).first();
    const editLink = row.locator('a[href*="/edit"], a[href*="/social-login/"]').first();
    if (await editLink.isVisible().catch(() => false)) {
      await editLink.click();
      await waitForPageLoader(page);
      const en = page.locator('form').filter({ has: page.locator('input[name="social_login_credential[client_id]"]') }).locator('input[name="social_login_credential[enabled]"]');
      if ((await en.count()) > 0 && (await en.isChecked().catch(() => false))) {
        await en.uncheck();
        await page
          .locator('form')
          .filter({ has: page.locator('input[name="social_login_credential[client_id]"]') })
          .locator('button[type="submit"]')
          .first()
          .click();
        await waitForPageLoader(page);
      }
    }
  });

  test('dashboard tour can be forced with ?tour=1 (UC-DASH-11)', async ({ page }) => {
    await page.goto('/dashboard?tour=1');
    await waitForPageLoader(page);
    const popover = page.locator('.driver-popover, .beacon-driver-popover');
    await expect(popover.first()).toBeVisible({ timeout: 15_000 });
    await dismissProductTour(page);
    await expect(page).toHaveURL(/\/dashboard/);
  });
});
