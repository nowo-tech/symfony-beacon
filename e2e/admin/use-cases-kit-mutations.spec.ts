import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from '../support/helpers';

test.describe('Admin kit mutations & deeper shells', () => {
  test('HTTP log export control is present (UC-ADM-21)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/http-log');
    await expect(page.locator('[data-testid="http-log-filters"], [data-testid="http-log-results"]').first()).toBeVisible();
    const exportBtn = page.locator('a[href*="/admin/http-log/export"], button').filter({ hasText: /export|exportar/i });
    await expect(exportBtn.first()).toBeVisible({ timeout: 15_000 });
  });

  test('dashboard menu new form can create ephemeral menu (UC-ADM-22)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const name = `e2e-menu-${suffix}`;
    await expectAuthenticatedPage(page, '/admin/menus/menu/new');
    const form = page.locator('form').filter({ has: page.locator('input[name*="[name]"], input[name*="[code]"], input[name*="[label]"]') }).first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    const nameField = form.locator('input[name*="[name]"], input[name*="[label]"]').first();
    await nameField.fill(name);
    const code = form.locator('input[name*="[code]"], input[name*="[slug]"]');
    if ((await code.count()) > 0) {
      await code.first().fill(`e2e_${suffix}`);
    }
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    // Best-effort: list contains the new menu or we landed on show.
    await page.goto('/admin/menus/');
    await dismissProductTour(page);
    await expect(page.getByRole('main')).toContainText(new RegExp(name.slice(0, 12), 'i'), { timeout: 15_000 });
  });

  test('breadcrumb kit new collection form loads (UC-ADM-23)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/breadcrumb-kit-admin/collections/new');
    await expect(page.locator('form').filter({ has: page.locator('input[name*="[name]"], input[name*="[code]"]') }).first()).toBeVisible({
      timeout: 15_000,
    });
  });

  test('RoutingKit create definition form is usable (UC-ADM-24)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    await expectAuthenticatedPage(page, '/admin/_routing/new');
    const form = page.locator('[data-testid="routing-kit-definition-form"]');
    await expect(form).toBeVisible({ timeout: 15_000 });
    const routeSelect = form.locator('select[name="route_name"]');
    const pathInput = form.locator('input[name="path"]');
    await expect(routeSelect).toBeVisible({ timeout: 10_000 });
    await expect(pathInput).toBeVisible();
    await pathInput.fill(`/e2e-kit-route-${suffix}`);

    const optionCount = await routeSelect.locator('option').count();
    if (optionCount === 0) {
      // No #[Routable] candidates in this install — mutation cannot complete; form shell is enough.
      return;
    }
    await routeSelect.selectOption({ index: 0 });
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('permissions create form submits ephemeral permission (UC-ADM-25)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    await expectAuthenticatedPage(page, '/admin/permissions');
    await page.locator('[data-testid="admin-permission-create"]').click();
    const dialog = page.locator('dialog[open], dialog.confirm-dialog[open]').filter({
      has: page.locator('input[name="admin_instance_permission_new[key]"]'),
    });
    await expect(dialog).toBeVisible({ timeout: 15_000 });
    await dialog.locator('input[name="admin_instance_permission_new[key]"]').fill(`e2e.perm.x${suffix}`);
    await dialog.locator('select[name="admin_instance_permission_new[category]"]').selectOption({ index: 1 });
    // Locale tabs hide non-active name_* inputs — fill the visible one (default locale may be es).
    const visibleName = dialog.locator('input[name*="admin_instance_permission_new[name_"]:visible').first();
    if ((await visibleName.count()) > 0) {
      await visibleName.fill(`E2E perm ${suffix}`);
    } else {
      await dialog.locator('input[name="admin_instance_permission_new[name_en]"]').fill(`E2E perm ${suffix}`, {
        force: true,
      });
    }
    await dialog.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(new RegExp(`e2e\\.perm\\.x${suffix}`, 'i'), { timeout: 15_000 });
  });

  test('roles create form submits ephemeral role (UC-ADM-26)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    await expectAuthenticatedPage(page, '/admin/roles?new=1');
    const form = page.locator('form').filter({ has: page.locator('input[name="admin_instance_role[name]"]') });
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="admin_instance_role[name]"]').fill(`E2E Role ${suffix}`);
    await form.locator('input[name="admin_instance_role[code]"]').fill(`e2e_role_${suffix}`);
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(new RegExp(`e2e_role_${suffix}|E2E Role ${suffix}`, 'i'), {
      timeout: 15_000,
    });
  });

  test('appearance theme section can save without error (UC-ADM-31)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/appearance/themes');
    const form = page.getByRole('main').locator('form').first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
  });

  test('OpenAPI HTML docs UI loads (UC-ING-17)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/api/doc');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('.swagger-ui, #swagger-ui, redoc, [id*="swagger"], main').first()).toBeVisible({
      timeout: 20_000,
    });
  });
});
