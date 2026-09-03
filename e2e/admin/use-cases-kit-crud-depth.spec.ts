import { test, expect, type Page } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from '../support/helpers';

/**
 * Full kit-admin CRUD depth — modal creates, edit, delete, export.
 * Complements shell-only coverage in use-cases-kit-mutations.spec.ts.
 */

async function openNewMenuModal(page: Page): Promise<void> {
  await expectAuthenticatedPage(page, '/admin/menus/');
  const open = page.locator('button[data-nowo-modal-target="modal-menu-new"]').first();
  if ((await open.count()) > 0) {
    await open.click();
  } else {
    await page.getByRole('button', { name: /new menu|nuevo menú/i }).first().click();
  }
  await expect(page.locator('dialog#modal-menu-new')).toBeVisible({ timeout: 10_000 });
}

async function fillMenuDefinition(form: ReturnType<Page['locator']>, code: string, name: string): Promise<void> {
  const codeInput = form.locator(
    'input[name*="[definition][code]"], input[name*="definition][code]"], input[name*="[code]"]',
  ).first();
  const nameInput = form.locator(
    'input[name*="[definition][name]"], input[name*="definition][name]"], input[name*="[name]"]',
  ).first();
  await expect(codeInput).toBeVisible({ timeout: 10_000 });
  await codeInput.fill(code);
  await nameInput.fill(name);
}

test.describe('Kit admin CRUD depth', () => {
  test('dashboard menu create via modal, edit identity, delete ephemeral (UC-ADM-22 depth)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const code = `e2e_menu_${suffix}`;
    const name = `E2E Menu ${suffix}`;
    const edited = `${name} edited`;

    await openNewMenuModal(page);
    const form = page.locator('dialog#modal-menu-new form').first();
    await fillMenuDefinition(form, code, name);
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/menus/);

    await page.goto('/admin/menus/');
    await dismissProductTour(page);
    const row = page.locator('tr').filter({ hasText: code }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });

    const editBtn = row.locator('button.btn-edit-menu[data-section="basic"]').first();
    await expect(editBtn).toBeVisible({ timeout: 10_000 });
    await editBtn.click();
    const editDialog = page.locator('dialog#modal-menu-form, dialog[open]').first();
    await expect(editDialog).toBeVisible({ timeout: 10_000 });
    const editForm = editDialog.locator('form').first();
    const nameField = editForm.locator('input[name*="[definition][name]"], input[name*="[name]"]').first();
    await nameField.fill(edited);
    await editForm.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(edited, { timeout: 15_000 });

    const deleteBtn = page.locator('tr').filter({ hasText: code }).locator('button.btn-delete-menu').first();
    await expect(deleteBtn).toBeVisible();
    await deleteBtn.click();
    const confirm = page.locator('dialog#modal-delete-confirm, dialog[open]').last();
    await expect(confirm).toBeVisible({ timeout: 10_000 });
    await confirm.locator('button[type="submit"], button.btn-danger, button.btn-primary').filter({ hasText: /delete|eliminar|borrar|confirm/i }).last().click();
    await waitForPageLoader(page);
    await expect(page.locator('tr').filter({ hasText: code })).toHaveCount(0, { timeout: 15_000 });
  });

  test('breadcrumb collection create full page, edit full page, delete ephemeral (UC-ADM-23 depth)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const code = `e2e_bk_${suffix}`;
    const name = `E2E BK ${suffix}`;

    await expectAuthenticatedPage(page, '/breadcrumb-kit-admin/collections/new');
    const create = page.locator('form').filter({ has: page.locator('input[name*="[code]"]') }).first();
    await expect(create).toBeVisible({ timeout: 15_000 });
    await create.locator('input[name*="[code]"]').first().fill(code);
    const nameField = create.locator('input[name*="[name]"]');
    if ((await nameField.count()) > 0) {
      await nameField.first().fill(name);
    }
    await create.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);

    let collectionId = page.url().match(/collections\/(\d+)/)?.[1] ?? '';
    if (!collectionId) {
      await page.goto(`/breadcrumb-kit-admin/collections?q=${encodeURIComponent(code)}`);
      await dismissProductTour(page);
      const row = page.locator('tr').filter({ hasText: code }).first();
      await expect(row).toBeVisible({ timeout: 15_000 });
      const itemsHref = await row.locator('a[href*="/collections/"]').first().getAttribute('href');
      collectionId = itemsHref?.match(/collections\/(\d+)/)?.[1] ?? '';
    }
    expect(collectionId, 'collection id').toBeTruthy();

    await page.goto(`/breadcrumb-kit-admin/collections/${collectionId}/edit`);
    await dismissProductTour(page);
    const editForm = page.locator('form').filter({ has: page.locator('input[name*="[code]"]') }).first();
    await expect(editForm).toBeVisible({ timeout: 15_000 });
    const editName = editForm.locator('input[name*="[name]"]').first();
    await editName.fill(`${name} edited`);
    await editForm.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(/actualizada|updated|colección/i, { timeout: 15_000 });

    await page.goto(`/breadcrumb-kit-admin/collections?q=${encodeURIComponent(code)}`);
    await dismissProductTour(page);
    await expect(page.locator('tr').filter({ hasText: code })).toContainText(`${name} edited`, { timeout: 15_000 });

    const delRow = page.locator('tr').filter({ hasText: code }).first();
    const deleteBtn = delRow.locator('button.btn-bk-delete').first();
    await expect(deleteBtn).toBeVisible({ timeout: 10_000 });
    await deleteBtn.click();
    const confirm = page.locator('#modal-bk-delete');
    await expect(confirm).toBeVisible({ timeout: 10_000 });
    await confirm.locator('#form-bk-delete-confirm button[type="submit"]').click({ force: true });
    await waitForPageLoader(page);
    await page.goto(`/breadcrumb-kit-admin/collections?q=${encodeURIComponent(code)}`);
    await dismissProductTour(page);
    await expect(page.locator('tr').filter({ hasText: code })).toHaveCount(0, { timeout: 15_000 });
  });

  test('RoutingKit create, edit path, delete ephemeral row (UC-ADM-24 depth)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const pathValue = `/e2e-rk-${suffix}`;
    const pathEdited = `${pathValue}-edited`;

    await expectAuthenticatedPage(page, '/admin/_routing/new');
    const form = page.locator('[data-testid="routing-kit-definition-form"] form, [data-testid="routing-kit-definition-form"]').first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    const routeSelect = form.locator('select[name="route_name"]');
    const pathInput = form.locator('input[name="path"]');
    const optionCount = await routeSelect.locator('option').count();
    if (optionCount === 0) {
      test.info().annotations.push({ type: 'note', description: 'no Routable candidates — skip mutation' });
      return;
    }
    await routeSelect.selectOption({ index: 0 });
    await pathInput.fill(pathValue);
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);

    await page.goto('/admin/_routing/');
    await dismissProductTour(page);
    const row = page.locator('tr').filter({ hasText: pathValue }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });

    const editLink = row.locator('a[href*="/edit/"]').first();
    await editLink.click();
    await waitForPageLoader(page);
    const editForm = page.locator('[data-testid="routing-kit-definition-form"] form, [data-testid="routing-kit-definition-form"]').first();
    await editForm.locator('input[name="path"]').fill(pathEdited);
    await editForm.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(pathEdited, { timeout: 15_000 });

    await page.goto('/admin/_routing/');
    await dismissProductTour(page);
    const delRow = page.locator('tr').filter({ hasText: pathEdited }).first();
    const deleteForm = delRow.locator('form[action*="/delete/"] button[type="submit"]').first();
    await expect(deleteForm).toBeVisible({ timeout: 10_000 });
    page.once('dialog', (d) => d.accept().catch(() => undefined));
    await deleteForm.click();
    await waitForPageLoader(page);
    await expect(page.locator('tr').filter({ hasText: pathEdited })).toHaveCount(0, { timeout: 15_000 });
  });

  test('HTTP log CSV export responds and routing cache clear works (UC-ADM-21 depth)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/admin/http-log');
    const exportBtn = page.locator('button.nowo-ui-btn-export, button').filter({ hasText: /export.*csv|csv/i }).first();
    await expect(exportBtn).toBeVisible({ timeout: 15_000 });

    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 30_000 }).catch(() => null),
      exportBtn.click(),
    ]);
    if (download) {
      expect(download.suggestedFilename()).toMatch(/\.csv|http|log/i);
    } else {
      // Some browsers stream inline — assert no 5xx flash.
      await waitForPageLoader(page);
      await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
    }

    await page.goto('/admin/_routing/');
    await dismissProductTour(page);
    const clearCache = page.locator('form[action*="/clear-cache"] button[type="submit"], button').filter({
      hasText: /clear cache|limpiar cach|cache/i,
    }).first();
    if ((await clearCache.count()) > 0) {
      await clearCache.click();
      await waitForPageLoader(page);
      await expect(page).not.toHaveURL(/\/login/);
      await expect(page.locator('body')).toContainText(/cache|cleared|limpiad|success|éxito|guardad/i);
    }
  });
});
