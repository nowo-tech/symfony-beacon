import { test, expect, type Page } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from '../support/helpers';

async function createEphemeralMenu(page: Page, suffix: string): Promise<{ code: string; menuId: string }> {
  const code = `e2e_menu_${suffix}`;
  const name = `E2E Menu ${suffix}`;
  await expectAuthenticatedPage(page, '/admin/menus/');
  const open = page.locator('button[data-nowo-modal-target="modal-menu-new"]').first();
  if ((await open.count()) > 0) {
    await open.click();
  } else {
    await page.getByRole('button', { name: /new menu|nuevo menú/i }).first().click();
  }
  await expect(page.locator('dialog#modal-menu-new')).toBeVisible({ timeout: 10_000 });
  const form = page.locator('dialog#modal-menu-new form').first();
  await form.locator('input[name*="[definition][code]"], input[name*="[code]"]').first().fill(code);
  await form.locator('input[name*="[definition][name]"], input[name*="[name]"]').first().fill(name);
  await form.locator('button[type="submit"]').first().click();
  await waitForPageLoader(page);
  await page.goto('/admin/menus/');
  await dismissProductTour(page);
  const row = page.locator('tr').filter({ hasText: code }).first();
  await expect(row).toBeVisible({ timeout: 15_000 });
  const href = await row.locator('a[href*="/admin/menus/"]').first().getAttribute('href');
  const menuId = href?.match(/\/admin\/menus\/(\d+)/)?.[1] ?? '';
  expect(menuId).toBeTruthy();
  return { code, menuId };
}

async function createEphemeralBreadcrumbCollection(page: Page, suffix: string): Promise<{ code: string; collectionId: string }> {
  const code = `e2e_bk_${suffix}`;
  const name = `E2E BK ${suffix}`;
  await expectAuthenticatedPage(page, '/breadcrumb-kit-admin/collections/new');
  const create = page.locator('form').filter({ has: page.locator('input[name*="[code]"]') }).first();
  await create.locator('input[name*="[code]"]').first().fill(code);
  const nameField = create.locator('input[name*="[name]"]');
  if ((await nameField.count()) > 0) {
    await nameField.first().fill(name);
  }
  await create.locator('button[type="submit"]').first().click();
  await waitForPageLoader(page);

  let collectionId = page.url().match(/collections\/(\d+)/)?.[1] ?? '';
  if (!collectionId) {
    await page.goto(`/breadcrumb-kit-admin/collections?q=${encodeURIComponent(code)}`);
    await dismissProductTour(page);
    const row = page.locator('tr').filter({ hasText: code }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    const itemsHref = await row.locator('a[href*="/collections/"]').first().getAttribute('href');
    collectionId = itemsHref?.match(/collections\/(\d+)/)?.[1] ?? '';
  }
  expect(collectionId).toBeTruthy();
  return { code, collectionId };
}

function openKitModal(page: Page, id: string) {
  return page.locator(`dialog#${id}[open]`).first();
}

async function waitForKitModal(page: Page, id: string) {
  const modal = page.locator(`dialog#${id}[open], #${id}[open]`).first();
  await expect(modal).toBeVisible({ timeout: 15_000 });
  return modal;
}

test.describe('Kit admin behavioral depth', () => {
  test('menu item create edit delete on show page (UC-ADM-22-D1)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const itemLabel = `E2E item ${suffix}`;
    const itemEdited = `${itemLabel} edited`;
    const { menuId } = await createEphemeralMenu(page, suffix);

    await page.goto(`/admin/menus/${menuId}`);
    await dismissProductTour(page);
    await page.locator('button.btn-add-item').first().click();
    const modal = await waitForKitModal(page, 'modal-item-form');
    await expect(modal.locator('#modal-item-form-body form, form')).toBeVisible({ timeout: 20_000 });
    const form = modal.locator('#modal-item-form-body form, form').first();
    await form.locator('input[name*="[basic][label]"], input[name*="[label]"]').first().fill(itemLabel);
    const routeSelect = form.locator('select[name*="[routeName]"], select[name*="route_name"]');
    if ((await routeSelect.count()) > 0) {
      const count = await routeSelect.locator('option').count();
      if (count > 1) {
        await routeSelect.selectOption({ index: 1 });
      }
    }
    await form.locator('button[type="submit"]').first().click({ force: true });
    await waitForPageLoader(page);
    await expect(page.locator('[data-testid="dashboard-menu-items-table"]')).toContainText(itemLabel, { timeout: 15_000 });

    const itemRow = page.locator('[data-testid="dashboard-menu-items-table"] tr').filter({ hasText: itemLabel }).first();
    await itemRow.locator('button.btn-edit-item[data-section="basic"]').first().click();
    await waitForKitModal(page, 'modal-item-form');
    const editForm = page.locator('#modal-item-form-body form, dialog#modal-item-form[open] form').first();
    await editForm.locator('input[name*="[basic][label]"], input[name*="[label]"]').first().fill(itemEdited);
    await editForm.locator('button[type="submit"]').first().click({ force: true });
    await waitForPageLoader(page);
    await expect(page.locator('[data-testid="dashboard-menu-items-table"]')).toContainText(itemEdited, { timeout: 15_000 });

    const delRow = page.locator('[data-testid="dashboard-menu-items-table"] tr').filter({ hasText: itemEdited }).first();
    await delRow.locator('button.btn-delete-item').first().click();
    const confirm = await waitForKitModal(page, 'modal-delete-item-confirm');
    await confirm.locator('button[type="submit"], button.btn-danger').filter({
      hasText: /delete|eliminar|borrar|confirm/i,
    }).last().click({ force: true });
    await waitForPageLoader(page);
    await expect(page.locator('[data-testid="dashboard-menu-items-table"] tr').filter({ hasText: itemEdited })).toHaveCount(0, {
      timeout: 15_000,
    });
  });

  test('menu JSON export and invalid import rejected (UC-ADM-22-D2)', async ({ page }) => {
    test.setTimeout(90_000);
    const suffix = Date.now().toString(36);
    const { menuId } = await createEphemeralMenu(page, suffix);

    const exportRes = await page.request.get(`/admin/menus/${menuId}/export`);
    expect(exportRes.status()).toBe(200);
    const body = await exportRes.text();
    expect(body.length).toBeGreaterThan(2);
    expect(() => JSON.parse(body)).not.toThrow();

    await page.goto('/admin/menus/');
    await dismissProductTour(page);
    await page.locator('button.btn-import').first().click();
    const importModal = await waitForKitModal(page, 'modal-import');
    await expect(importModal.locator('#modal-import-body input[type="file"], [data-import-form] input[type="file"]')).toBeVisible({
      timeout: 20_000,
    });
    const fileInput = importModal.locator('#modal-import-body input[type="file"], [data-import-form] input[type="file"]').first();
    await fileInput.setInputFiles({
      name: 'e2e-bad-menu.json',
      mimeType: 'application/json',
      buffer: Buffer.from('{ definitely not valid json'),
    });
    await importModal.locator('button[type="submit"]').first().click({ force: true });
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/invalid|inválid|json|error|formato/i, { timeout: 15_000 });
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
  });

  test('breadcrumb item create edit delete in collection (UC-ADM-23-D1)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const itemLabel = `BK item ${suffix}`;
    const routeName = `e2e_bk_item_${suffix}`;
    const { collectionId } = await createEphemeralBreadcrumbCollection(page, suffix);
    await page.goto(`/breadcrumb-kit-admin/collections/${collectionId}/items/new`);
    await dismissProductTour(page);
    const form = page.locator('form').filter({
      has: page.locator('input[name="breadcrumb_item[routeName]"]'),
    }).first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="breadcrumb_item[routeName]"]').fill(routeName);
    await form.locator('input[name="breadcrumb_item[label]"]').fill(itemLabel);
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    // BreadcrumbKit redirectToRefererOr may bounce to /items/new after create — assert via filtered list.
    await page.goto(
      `/breadcrumb-kit-admin/collections/${collectionId}/items?q=${encodeURIComponent(routeName)}`,
    );
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="breadcrumb-kit-items-table"]')).toContainText(itemLabel, {
      timeout: 15_000,
    });

    const row = page.locator('[data-testid="breadcrumb-kit-items-table"] tr').filter({ hasText: itemLabel }).first();
    const bkUrl = await row.locator('button.btn-bk-item-form').first().getAttribute('data-bk-url');
    const itemId = bkUrl?.match(/items\/(\d+)\/edit/)?.[1];
    expect(itemId, 'breadcrumb item id').toBeTruthy();
    await page.goto(`/breadcrumb-kit-admin/collections/${collectionId}/items/${itemId}/edit`);
    await dismissProductTour(page);
    const editForm = page.locator('form').filter({
      has: page.locator('input[name="breadcrumb_item[label]"]'),
    }).first();
    await expect(editForm).toBeVisible({ timeout: 15_000 });
    const editedLabel = `${itemLabel} edited`;
    await editForm.locator('input[name="breadcrumb_item[label]"]').fill(editedLabel);
    await editForm.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(/actualiz|updated|editad|item/i, { timeout: 15_000 });

    await page.goto(
      `/breadcrumb-kit-admin/collections/${collectionId}/items?q=${encodeURIComponent(routeName)}`,
    );
    await dismissProductTour(page);
    const delRow = page.locator('[data-testid="breadcrumb-kit-items-table"] tr').filter({ hasText: editedLabel }).first();
    await expect(delRow).toBeVisible({ timeout: 15_000 });
    const deleteBtn = delRow.locator('button.btn-bk-delete').first();
    await expect(deleteBtn).toBeVisible({ timeout: 10_000 });
    await deleteBtn.click();
    const confirm = page.locator('#modal-bk-delete');
    await expect(confirm).toBeVisible({ timeout: 15_000 });
    await confirm.locator('#form-bk-delete-confirm button[type="submit"]').click({ force: true });
    await waitForPageLoader(page);
    await page.goto(
      `/breadcrumb-kit-admin/collections/${collectionId}/items?q=${encodeURIComponent(routeName)}`,
    );
    await dismissProductTour(page);
    await expect(
      page.locator('[data-testid="breadcrumb-kit-items-table"] tr').filter({ hasText: routeName }),
    ).toHaveCount(0, { timeout: 15_000 });
  });

  test('RoutingKit duplicate path surfaces conflicts (UC-ADM-24-D1)', async ({ page }) => {
    test.setTimeout(90_000);
    const suffix = Date.now().toString(36);
    const pathValue = `/e2e-rk-dup-${suffix}`;

    await expectAuthenticatedPage(page, '/admin/_routing/new');
    const form = page.locator('[data-testid="routing-kit-definition-form"] form, [data-testid="routing-kit-definition-form"]').first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    const routeSelect = form.locator('select[name="route_name"]');
    const optionCount = await routeSelect.locator('option').count();
    if (optionCount === 0) {
      test.info().annotations.push({ type: 'note', description: 'no Routable candidates — skip' });
      return;
    }
    await routeSelect.selectOption({ index: 0 });
    await form.locator('input[name="path"]').fill(pathValue);
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);

    await page.goto('/admin/_routing/new');
    await dismissProductTour(page);
    const form2 = page.locator('[data-testid="routing-kit-definition-form"] form, [data-testid="routing-kit-definition-form"]').first();
    await routeSelect.selectOption({ index: 0 });
    await form2.locator('input[name="path"]').fill(pathValue);
    await form2.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/conflict|conflicto|duplicate|duplicad|already|ya existe/i, { timeout: 15_000 });
  });
});
