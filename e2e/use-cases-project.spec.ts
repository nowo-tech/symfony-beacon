import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, resolveDemoProjectUuid } from './helpers';

test.describe('Project settings — use cases', () => {
  test('settings section tabs load individually (UC-PROJ-04)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    // Route slugs: general | access | alerts | data | danger
    for (const section of ['general', 'access', 'alerts', 'data', 'danger']) {
      await expectAuthenticatedPage(page, `/projects/${uuid}/settings/${section}`);
      await expect(page.getByRole('main')).toBeVisible();
    }
  });

  test('API keys panel lists seeded key and create form (UC-PROJ-05)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    await expect(page.getByRole('main')).toBeVisible();
    const markers = page.locator(
      `[data-testid="api-key-dsn-redacted"], [data-testid="api-key-dsn"], form[action*="/projects/${uuid}/keys"]`,
    );
    await expect(markers.first()).toBeAttached();
  });

  test('members panel renders on access tab (UC-PROJ-06 shell)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/settings/access`);
    await expect(page.getByRole('main')).toBeVisible();
    await expect(page.getByRole('main')).toContainText(/member|miembro|role|rol/i);
  });

  test('danger zone section is present for owner (UC-PROJ-17 shell)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/settings/danger`);
    await expect(page.getByRole('main')).toBeVisible();
    // Confirm-dialog forms stay hidden until opened — assert panel shell + form attachment.
    await expect(
      page.locator('.panel--danger, section.panel').filter({ hasText: /danger|peligro|delete|eliminar/i }).first(),
    ).toBeVisible();
    await expect(
      page.locator('form[action*="clear-history"], form[action*="transfer"], form[action*="delete"]').first(),
    ).toBeAttached();
  });

  test('notification create form exposes type field (UC-NOTIF-01)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/notifications/new`);
    const form = page.getByRole('main').locator('form.notification-destination-form, form.panel').first();
    await expect(form).toBeVisible();
    await expect(form.locator('select, input').first()).toBeVisible();
  });

  test('threshold create form exposes metric fields (UC-NOTIF-06)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/threshold-rules/new`);
    const form = page.getByRole('main').locator('form.panel, form').first();
    await expect(form).toBeVisible();
    await expect(form.locator('input, select').first()).toBeVisible();
  });
});
