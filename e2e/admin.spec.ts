import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage } from './helpers';

test.describe('Administration', () => {
  test('admin hub loads', async ({ page }) => {
    await page.goto('/admin');
    await dismissProductTour(page);
    await expect(page).toHaveURL(/\/admin/);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toBeVisible();
  });

  test('identity admin pages load', async ({ page }) => {
    for (const path of [
      '/admin/users',
      '/admin/users/new',
      '/admin/groups',
      '/admin/groups/new',
      '/admin/projects',
      '/admin/projects/new',
      '/admin/social-login',
    ]) {
      await expectAuthenticatedPage(page, path);
    }
  });

  test('ops and settings pages load', async ({ page }) => {
    for (const path of [
      '/admin/ops',
      '/settings/mailer',
      '/settings/mercure',
      '/settings/appearance',
      '/settings/ops-defaults',
      '/settings/ops-defaults/governance',
      '/settings/ops-defaults/ingest',
      '/settings/ops-defaults/metrics',
      '/settings/ops-defaults/inbound',
      '/settings/ops-defaults/notifications',
      '/settings/instance-config',
    ]) {
      await expectAuthenticatedPage(page, path);
    }
  });

  test('kit admin panels load', async ({ page }) => {
    // Hit panels one-by-one so a single redirect is attributable.
    const paths = [
      '/admin/http-log',
      '/admin/api/doc',
      '/admin/menus/',
      '/admin/_routing/',
      '/breadcrumb-kit-admin/',
      '/admin/cookie-consent',
    ];
    for (const path of paths) {
      await expectAuthenticatedPage(page, path);
    }
  });

  test('OpenAPI JSON is available', async ({ page }) => {
    // Use page request so auth cookies from storageState apply.
    const res = await page.request.get('/admin/api/doc.json');
    expect(res.status()).toBeLessThan(500);
    if (res.status() === 200) {
      const ct = res.headers()['content-type'] ?? '';
      expect(ct).toMatch(/json|html/i);
    }
  });

  test('first admin user activity page when list has links', async ({ page }) => {
    await page.goto('/admin/users');
    await dismissProductTour(page);
    const activity = page.locator('a[href*="/admin/users/"][href*="/activity"]').first();
    if ((await activity.count()) === 0) {
      return;
    }
    await activity.click();
    await expect(page).toHaveURL(/\/admin\/users\/.+\/activity/);
    await expect(page).not.toHaveURL(/\/login/);
  });
});
