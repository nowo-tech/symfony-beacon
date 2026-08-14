import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from '../support/helpers';

test.describe('Kit admin deep checks', () => {
  test('HTTP log filters and results shell', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/http-log');
    await expect(page.locator('[data-testid="http-log-filters"]')).toBeVisible();
    await expect(page.locator('[data-testid="http-log-results"]')).toBeVisible();

    const clear = page.locator('[data-testid="http-log-filters"] a, [data-testid="http-log-filters"] button').filter({
      hasText: /clear|limpiar|effacer|zurücksetzen|wissen|reset/i,
    });
    if ((await clear.count()) > 0) {
      await expect(clear.first()).toBeVisible();
    }

    const detail = page.locator('a[href*="/admin/http-log/"]').filter({ hasNot: page.locator('[href$="/admin/http-log"]') }).first();
    if ((await detail.count()) > 0) {
      const href = await detail.getAttribute('href');
      if (href && /\/admin\/http-log\/\d+/.test(href)) {
        await detail.click();
        await waitForPageLoader(page);
        await expect(page).toHaveURL(/\/admin\/http-log\/\d+/);
        await expect(page.locator('[data-testid="http-log-show"]')).toBeVisible();
      }
    }
  });

  test('RoutingKit panel table and new form', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/_routing/');
    await expect(page.locator('[data-testid="routing-kit-definitions-table"]')).toBeVisible();

    await expectAuthenticatedPage(page, '/admin/_routing/new');
    await expect(page.locator('[data-testid="routing-kit-definition-form"]')).toBeVisible();
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
  });

  test('dashboard menus list and first menu show', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/menus/');
    await expect(page.locator('[data-testid="dashboard-menu-menus-table"]')).toBeVisible();

    const hrefs = await page.locator('a[href*="/admin/menus/"]').evaluateAll((anchors) =>
      anchors
        .map((a) => (a as HTMLAnchorElement).getAttribute('href') ?? '')
        .filter((href) => /\/admin\/menus\/\d+(\/|$)/.test(href) && !/\/(export|import|edit|copy)/.test(href)),
    );
    if (hrefs.length === 0) {
      return;
    }
    await page.goto(hrefs[0]);
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="dashboard-menu-items-table"], [data-testid="dashboard-menu-sortable"]').first()).toBeVisible();
  });

  test('breadcrumb kit collections table', async ({ page }) => {
    await expectAuthenticatedPage(page, '/breadcrumb-kit-admin/');
    // Index may redirect to collections — either shell is fine.
    const collections = page.locator('[data-testid="breadcrumb-kit-collections-table"]');
    if ((await collections.count()) === 0) {
      await expectAuthenticatedPage(page, '/breadcrumb-kit-admin/collections');
    }
    await expect(page.locator('[data-testid="breadcrumb-kit-collections-table"]')).toBeVisible();
  });

  test('cookie consent admin settings and definitions', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/cookie-consent');
    const settings = page.locator('[data-testid="cookie-consent-settings-form"]');
    const defs = page.locator('[data-testid="cookie-consent-definitions-table"]');
    // Landing may be settings or definitions depending on kit version.
    if ((await settings.count()) > 0) {
      await expect(settings).toBeVisible();
    } else if ((await defs.count()) > 0) {
      await expect(defs).toBeVisible();
    } else {
      await expect(page.getByRole('main')).toBeVisible();
    }
  });
});
