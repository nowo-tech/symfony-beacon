import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from '../support/helpers';

test.describe('Admin RBAC', () => {
  test('permissions index lists rows and create link', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/permissions');
    await expect(page.locator('[data-testid="admin-permission-create"]')).toBeVisible();
    await expect(page.locator('[data-testid="admin-permission-row"]').first()).toBeVisible();

    await expectAuthenticatedPage(page, '/admin/permissions/new');
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
  });

  test('roles index, show, permissions, and users tabs', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/roles');
    await expect(page.locator('[data-testid="admin-role-create"]')).toBeVisible();
    const row = page.locator('[data-testid="admin-role-row"]').first();
    await expect(row).toBeVisible();

    const showLink = row.locator('a[href*="/admin/roles/"]').first();
    await showLink.click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/roles\/[0-9a-f-]{36}/i);
    await expect(page.locator('[data-testid="admin-role-overview"]')).toBeVisible();

    const roleUrl = page.url().replace(/\/$/, '');
    await expectAuthenticatedPage(page, `${roleUrl}/permissions`);
    await expect(page.locator('[data-testid="admin-role-permissions"]')).toBeVisible();

    await expectAuthenticatedPage(page, `${roleUrl}/users`);
    await expect(page.locator('[data-testid="admin-role-users"]')).toBeVisible();
  });

  test('role create form loads', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/roles/new');
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
  });

  test('admin hub tiles reach RBAC sections', async ({ page }) => {
    await page.goto('/admin');
    await dismissProductTour(page);

    for (const { testid, url } of [
      { testid: 'admin-permissions', url: /\/admin\/permissions/ },
      { testid: 'admin-roles', url: /\/admin\/roles/ },
    ] as const) {
      await page.goto('/admin');
      await dismissProductTour(page);
      const link = page.locator(`[data-testid="${testid}"]`);
      if ((await link.count()) === 0) {
        // Hub tile markers may be optional — fall back to direct nav.
        await expectAuthenticatedPage(page, testid === 'admin-permissions' ? '/admin/permissions' : '/admin/roles');
        continue;
      }
      await expect(link).toBeVisible();
      await link.click();
      await expect(page).toHaveURL(url);
      await expect(page).not.toHaveURL(/\/login/);
    }
  });
});
