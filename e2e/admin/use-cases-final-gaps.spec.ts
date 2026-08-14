import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import {
  dismissCookieConsent,
  dismissProductTour,
  expectAuthenticatedPage,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

async function createEnabledUser(
  page: import('@playwright/test').Page,
  email: string,
  password: string,
  displayName: string,
): Promise<void> {
  await page.goto('/admin/users?new=1');
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

test.describe('Final gaps — full role, admin members, bulk import', () => {
  test('full membership can manage settings but not transfer ownership (UC-PROJ-24)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.full.${suffix}@example.invalid`;
    const password = `E2eFull1!${suffix}`;

    await createEnabledUser(page, email, password, `Full ${suffix}`);
    const uuid = await resolveDemoProjectUuid(page);

    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const members = page.locator('section.panel').filter({ hasText: /member|miembro/i }).first();
    await members.locator('button[data-action="confirm-dialog#open"]').first().click();
    const addForm = page.locator('form').filter({ has: page.locator('#member-email, input[name="project_member_add[email]"]') });
    await expect(addForm).toBeVisible({ timeout: 10_000 });
    await addForm.locator('#member-email, input[name="project_member_add[email]"]').fill(email);
    await addForm.locator('#member-add-role, select[name="project_member_add[role]"]').selectOption('full');
    await addForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(email, { timeout: 15_000 });

    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const full = await ctx.newPage();
    try {
      await full.goto('/login');
      await dismissCookieConsent(full);
      await full.locator('input[name="login_form[_username]"]').fill(email);
      await full.locator('input[name="login_form[_password]"]').fill(password);
      await full
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await full.waitForURL(/\/dashboard/, { timeout: 45_000 });
      await dismissProductTour(full);

      // Full ≈ owner for settings / danger delete, but not primary-owner transfer.
      await full.goto(`/projects/${uuid}/settings/general`);
      await dismissProductTour(full);
      await expect(full).not.toHaveURL(/\/login/);
      await expect(full.getByRole('main')).toBeVisible();

      await full.goto(`/projects/${uuid}/settings/danger`);
      await dismissProductTour(full);
      // Full can open delete (canDeleteProject); transfer is primary-owner only (trigger absent).
      const danger = full.locator('section.panel--danger, section.panel').filter({ hasText: /danger|peligro|zone/i }).first();
      await expect(danger).toBeVisible({ timeout: 15_000 });
      await expect(
        danger.locator('button[data-action="confirm-dialog#open"], button').filter({ hasText: /delete|eliminar|borrar/i }).first(),
      ).toBeVisible({ timeout: 10_000 });
      await expect(
        danger.locator('button').filter({ hasText: /transfer|transferir|ownership|propiedad/i }),
      ).toHaveCount(0);
    } finally {
      await ctx.close();
    }

    // Cleanup: owner removes full member.
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const row = page.locator('li').filter({ hasText: email }).first();
    if (await row.isVisible().catch(() => false)) {
      const remove = row.locator('button[data-action="confirm-dialog#open"], form[action*="/remove"] button').last();
      if (await remove.isVisible().catch(() => false)) {
        await remove.click();
        const dialog = page.locator('dialog[open]').last();
        if (await dialog.isVisible().catch(() => false)) {
          await dialog.locator('button[type="submit"]').click();
          await waitForPageLoader(page);
        }
      }
    }
  });

  test('admin project add/update/remove member and link group (UC-ADM-29)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.admmem.${suffix}@example.invalid`;
    const password = `E2eAdmMem1!${suffix}`;

    await createEnabledUser(page, email, password, `AdmMem ${suffix}`);

    // Prefer ephemeral admin project so we do not churn demo memberships.
    await expectAuthenticatedPage(page, '/admin/projects/new');
    const name = `E2E Adm Members ${suffix}`;
    const form = page.getByRole('main').locator('form').filter({ has: page.locator('input[name="project[name]"]') });
    await form.locator('input[name="project[name]"]').fill(name);
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    let uuid = page.url().match(/\/admin\/projects\/([0-9a-f-]{36})/i)?.[1];
    if (!uuid) {
      await page.goto('/admin/projects');
      const href = await page.locator('a[href*="/admin/projects/"]').filter({ hasText: name }).first().getAttribute('href');
      uuid = href?.match(/\/admin\/projects\/([0-9a-f-]{36})/i)?.[1];
    }
    expect(uuid).toBeTruthy();

    await page.goto(`/admin/projects/${uuid}`);
    await dismissProductTour(page);
    const addMember = page.locator('form').filter({ has: page.locator('input[name="project_member_add[email]"]') });
    await expect(addMember).toBeVisible({ timeout: 15_000 });
    await addMember.locator('input[name="project_member_add[email]"]').fill(email);
    await addMember.locator('select[name="project_member_add[role]"]').selectOption('member');
    await addMember.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(email, { timeout: 15_000 });

    const row = page.locator('li').filter({ hasText: email }).first();
    const roleForm = row.locator('form').filter({ has: page.locator('select[name*="[role]"]') }).first();
    if (await roleForm.isVisible().catch(() => false)) {
      await roleForm.locator('select[name*="[role]"]').selectOption('admin');
      await roleForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
      await expect(row).toContainText(/admin|administrador/i);
    }

    const remove = row.locator('form[action*="remove"], form').filter({ has: page.locator('button') }).last();
    page.once('dialog', (d) => d.accept().catch(() => undefined));
    await remove.locator('button[type="submit"]').click({ force: true });
    await waitForPageLoader(page);

    // Group link when a group exists.
    const addGroup = page.locator('form').filter({ has: page.locator('select[name="project_group_add[group]"]') });
    if (await addGroup.isVisible().catch(() => false)) {
      const opts = addGroup.locator('select[name="project_group_add[group]"] option:not([value=""])');
      if ((await opts.count()) > 0) {
        await addGroup.locator('select[name="project_group_add[group]"]').selectOption({ index: 1 });
        await addGroup.locator('select[name="project_group_add[role]"]').selectOption('member');
        await addGroup.locator('button[type="submit"]').click();
        await waitForPageLoader(page);
        await expect(page).not.toHaveURL(/\/login/);
      }
    }
  });

  test('admin projects bulk export then import round-trip (UC-ADM-30)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/admin/projects');
    await dismissProductTour(page);

    // Scope to projects portability — do not click sidebar "Exportar / importar" (instance-config).
    const portability = page.locator('[data-testid="admin-projects-config-portability"]');
    await expect(portability).toBeVisible({ timeout: 15_000 });
    await portability.locator('#tab-admin-projects-config-export').click();

    const downloadBtn = portability.locator('[data-testid="admin-projects-config-download"]');
    await expect(downloadBtn).toBeVisible({ timeout: 15_000 });
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 30_000 }),
      downloadBtn.click(),
    ]);
    const tmp = path.join(os.tmpdir(), `beacon-admin-projects-${Date.now()}.json`);
    await download.saveAs(tmp);
    expect(fs.existsSync(tmp)).toBeTruthy();
    const raw = fs.readFileSync(tmp, 'utf8');
    expect(raw.length).toBeGreaterThan(10);
    JSON.parse(raw); // valid JSON bundle

    await portability.locator('#tab-admin-projects-config-import').click();
    const importForm = portability.locator('[data-testid="admin-projects-config-import-form"]');
    await expect(importForm).toBeVisible({ timeout: 15_000 });
    await importForm.locator('input[type="file"]').setInputFiles(tmp);
    await importForm.locator('[data-testid="admin-projects-config-import-submit"]').click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
    fs.unlinkSync(tmp);
  });
});
