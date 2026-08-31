import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, gotoStable, waitForPageLoader } from '../support/helpers';

/**
 * RBAC depth — permission/role edit + delete after create.
 * Complements shell create in use-cases-kit-mutations and matrix/guards in atomic-gaps.
 */

test.describe('Admin RBAC depth', () => {
  test('permission create edit delete ephemeral (UC-ADM-25-D1)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const key = `e2e.perm.d${suffix}`;
    const name = `E2E perm ${suffix}`;
    const nameEdited = `${name} edited`;

    await expectAuthenticatedPage(page, '/admin/permissions');
    await page.locator('[data-testid="admin-permission-create"]').click();
    const createDialog = page.locator('dialog[open], dialog.confirm-dialog[open]').filter({
      has: page.locator('input[name="admin_instance_permission_new[key]"]'),
    });
    await expect(createDialog).toBeVisible({ timeout: 15_000 });
    await createDialog.locator('input[name="admin_instance_permission_new[key]"]').fill(key);
    await createDialog.locator('select[name="admin_instance_permission_new[category]"]').selectOption({ index: 1 });
    const visibleName = createDialog.locator('input[name*="admin_instance_permission_new[name_"]:visible').first();
    if ((await visibleName.count()) > 0) {
      await visibleName.fill(name);
    } else {
      await createDialog.locator('input[name="admin_instance_permission_new[name_en]"]').fill(name, { force: true });
    }
    await createDialog.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(new RegExp(key.replace(/\./g, '\\.'), 'i'), { timeout: 15_000 });

    await page.goto(`/admin/permissions?q=${encodeURIComponent(key)}`);
    await dismissProductTour(page);
    const row = page.locator('[data-testid="admin-permission-row"], tr').filter({ hasText: key }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    await row.locator('[data-testid="admin-permission-edit"]').click();
    const editDialog = page.locator('dialog[open], dialog.confirm-dialog[open]').filter({
      has: page.locator('input[name*="admin_instance_permission_"][name*="[name_"]'),
    });
    await expect(editDialog).toBeVisible({ timeout: 15_000 });
    const editName = editDialog.locator('input[name*="[name_"]:visible').first();
    if ((await editName.count()) > 0) {
      await editName.fill(nameEdited);
    } else {
      await editDialog.locator('input[name*="[name_en]"]').fill(nameEdited, { force: true });
    }
    await editDialog.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(nameEdited, { timeout: 15_000 });

    await page.goto(`/admin/permissions?q=${encodeURIComponent(key)}`);
    await dismissProductTour(page);
    const delRow = page.locator('[data-testid="admin-permission-row"], tr').filter({ hasText: key }).first();
    const deleteBtn = delRow.locator('form[action*="/delete"] button[type="submit"], button.btn-danger').first();
    await expect(deleteBtn).toBeVisible({ timeout: 10_000 });
    page.once('dialog', (d) => d.accept().catch(() => undefined));
    await deleteBtn.click({ force: true });
    await waitForPageLoader(page);
    await expect(page.locator('[data-testid="admin-permission-row"], tr').filter({ hasText: key })).toHaveCount(0, {
      timeout: 15_000,
    });
  });

  test('role create edit delete ephemeral (UC-ADM-26-D1)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const code = `e2e_role_d${suffix}`;
    const name = `E2E Role D ${suffix}`;
    const nameEdited = `${name} edited`;

    await expectAuthenticatedPage(page, '/admin/roles?new=1');
    const create = page.locator('form').filter({ has: page.locator('input[name="admin_instance_role[name]"]') });
    await expect(create).toBeVisible({ timeout: 15_000 });
    await create.locator('input[name="admin_instance_role[name]"]').fill(name);
    await create.locator('input[name="admin_instance_role[code]"]').fill(code);
    await create.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    const uuid = page.url().match(/\/admin\/roles\/([0-9a-f-]{36})/i)?.[1];
    expect(uuid, 'role uuid after create').toBeTruthy();

    await gotoStable(page, `/admin/roles/${uuid}`);
    await dismissProductTour(page);
    await page.locator('[data-testid="admin-role-edit"]').click();
    const editDialog = page.locator('dialog[open], dialog.confirm-dialog[open]').filter({
      has: page.locator('input[name="admin_instance_role[name]"]'),
    });
    await expect(editDialog).toBeVisible({ timeout: 15_000 });
    await editDialog.locator('input[name="admin_instance_role[name]"]').fill(nameEdited);
    await editDialog.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(nameEdited, { timeout: 15_000 });
    await expect(page.getByTestId('admin-role-overview')).toContainText(/ROLE_E2E_ROLE/i);

    const del = page.locator('form[action*="/delete"] button[type="submit"]').first();
    await expect(del).toBeVisible({ timeout: 10_000 });
    page.once('dialog', (d) => d.accept().catch(() => undefined));
    await del.click({ force: true });
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/roles/, { timeout: 15_000 });
    await expect(page.locator('body')).not.toContainText(code, { timeout: 15_000 });
  });
});
