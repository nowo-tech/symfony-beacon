import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from '../support/helpers';

/**
 * Kit negative / edge depth: RoutingKit method + conflicts API, empty path,
 * breadcrumb invalid import (ADM-23/24).
 */

test.describe('RoutingKit negatives depth', () => {
  test('POST-only panel actions reject GET with 405 (UC-ADM-24-D4)', async ({ page }) => {
    test.setTimeout(60_000);
    await expectAuthenticatedPage(page, '/admin/_routing/');
    for (const path of ['/admin/_routing/export', '/admin/_routing/import', '/admin/_routing/clear-cache']) {
      const res = await page.request.get(path, { failOnStatusCode: false });
      expect(res.status(), path).toBe(405);
    }
  });

  test('conflicts preview JSON returns array for candidate path (UC-ADM-24-D5)', async ({ page }) => {
    test.setTimeout(60_000);
    await expectAuthenticatedPage(page, '/admin/_routing/new');
    const form = page.locator('[data-testid="routing-kit-definition-form"] form, [data-testid="routing-kit-definition-form"]').first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    const routeSelect = form.locator('select[name="route_name"]');
    const optionCount = await routeSelect.locator('option').count();
    if (optionCount === 0) {
      test.info().annotations.push({ type: 'note', description: 'no Routable candidates — skip' });
      return;
    }
    const routeName = await routeSelect.locator('option').nth(0).getAttribute('value');
    expect(routeName).toBeTruthy();
    const suffix = Date.now().toString(36);
    const pathValue = `/e2e-rk-conflict-preview-${suffix}`;
    const res = await page.request.get('/admin/_routing/conflicts', {
      params: {
        route_name: routeName!,
        path: pathValue,
        locale: '',
      },
      failOnStatusCode: false,
    });
    expect(res.status(), await res.text()).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('conflicts');
    expect(Array.isArray(body.conflicts)).toBeTruthy();
  });

  test('empty path on create is rejected without 5xx (UC-ADM-24-D6)', async ({ page }) => {
    test.setTimeout(60_000);
    await expectAuthenticatedPage(page, '/admin/_routing/new');
    const form = page.locator('[data-testid="routing-kit-definition-form"] form, [data-testid="routing-kit-definition-form"]').first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    const routeSelect = form.locator('select[name="route_name"]');
    if ((await routeSelect.locator('option').count()) === 0) {
      test.info().annotations.push({ type: 'note', description: 'no Routable candidates — skip' });
      return;
    }
    await routeSelect.selectOption({ index: 0 });
    await form.locator('input[name="path"]').fill('');
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/_routing\/new/, { timeout: 15_000 });
    await expect(page.locator('body')).toContainText(/required|obligator|path|ruta|blank|vacío|invalid|inválid/i, {
      timeout: 15_000,
    });
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
  });
});

test.describe('BreadcrumbKit negatives depth', () => {
  test('invalid breadcrumb import JSON is rejected (UC-ADM-23-D3)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/breadcrumb-kit-admin/collections');
    await page.locator('button.btn-bk-import').first().click();
    const modal = page.locator('#modal-bk-import');
    await expect(modal).toBeVisible({ timeout: 15_000 });
    await expect(modal.locator('#modal-bk-import-body form, [data-import-form], form')).toBeVisible({
      timeout: 20_000,
    });
    const fileInput = modal.locator('#modal-bk-import-body input[type="file"], [data-import-form] input[type="file"], input[type="file"]').first();
    await fileInput.setInputFiles({
      name: 'e2e-bad-breadcrumb.json',
      mimeType: 'application/json',
      buffer: Buffer.from('{ definitely not valid breadcrumb json'),
    });
    await modal.locator('button[type="submit"]').first().click({ force: true });
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/invalid|inválid|json|error|formato|import/i, {
      timeout: 15_000,
    });
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
  });
});
