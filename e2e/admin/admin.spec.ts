import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, resolveDemoProjectUuid } from '../support/helpers';

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
      '/admin/mailer',
      '/admin/mercure',
      '/admin/appearance',
      '/admin/ops-defaults',
      '/admin/ops-defaults/governance',
      '/admin/ops-defaults/ingest',
      '/admin/ops-defaults/metrics',
      '/admin/ops-defaults/inbound',
      '/admin/ops-defaults/notifications',
      '/admin/instance-config',
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
      '/breadcrumb-kit-admin/',
      '/admin/cookie-consent',
      '/admin/_routing/',
    ];
    for (const path of paths) {
      await expectAuthenticatedPage(page, path);
    }
  });

  test('OpenAPI JSON is available', async ({ page }) => {
    // Use page request so auth cookies from storageState apply.
    const res = await page.request.get('/admin/api/doc.json');
    expect(res.status()).toBe(200);
    const ct = res.headers()['content-type'] ?? '';
    expect(ct).toMatch(/json/i);
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

  test('admin projects list and first project show load', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/projects');
    await expect(page.locator('[data-testid="admin-projects-config-portability"]')).toBeVisible();

    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/admin/projects/${uuid}`);
    await expect(page.locator('[data-testid="admin-project-show"]')).toBeVisible();
  });

  test('admin group show when list has entries', async ({ page }) => {
    await page.goto('/admin/groups');
    await dismissProductTour(page);
    const hrefs = await page.locator('a[href*="/admin/groups/"]').evaluateAll((anchors) =>
      anchors
        .map((a) => (a as HTMLAnchorElement).getAttribute('href') ?? '')
        .filter((href) => /\/admin\/groups\/[0-9a-f-]{36}/i.test(href)),
    );
    if (hrefs.length === 0) {
      return;
    }
    await page.goto(hrefs[0]);
    await dismissProductTour(page);
    await expect(page).toHaveURL(/\/admin\/groups\/[0-9a-f-]{36}/i);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.getByRole('main')).toBeVisible();
  });
});

