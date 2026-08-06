import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from './helpers';

test.describe('Dashboard aside panels', () => {
  test('summary shows metric cards', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard/summary');
    const cards = page.locator('[data-testid="summary-cards"]');
    await expect(cards).toBeVisible();
    await expect(cards.locator('a.panel, div.panel')).toHaveCount(6);
  });

  test('summary card navigates to assignments', async ({ page }) => {
    await page.goto('/dashboard/summary');
    await dismissProductTour(page);
    await page.locator('[data-testid="summary-cards"] a[href*="/dashboard/assignments"]').first().click();
    await expect(page).toHaveURL(/\/dashboard\/assignments/);
    await expect(page.locator('[data-testid="assignments-filters"]')).toBeVisible();
  });

  test('assignments filters update the query string', async ({ page }) => {
    await page.goto('/dashboard/assignments');
    await dismissProductTour(page);
    const filters = page.locator('[data-testid="assignments-filters"]');
    await expect(filters).toBeVisible();

    await filters.locator('select[name="scope"]').selectOption('mine');
    await filters.locator('select[name="status"]').selectOption('unresolved');
    await filters.locator('button[type="submit"]').click();
    await waitForPageLoader(page);

    await expect(page).toHaveURL(/[?&]scope=mine/);
    await expect(page).toHaveURL(/[?&]status=unresolved/);
    await expect(page.locator('[data-testid="assignments-empty"], [data-testid="assignments-results"]').first()).toBeVisible();
  });

  test('mentions, activity, alerts, and new-in-release show filter shells', async ({ page }) => {
    const cases = [
      { path: '/dashboard/mentions', filters: 'mentions-filters', result: 'mentions' },
      { path: '/dashboard/activity', filters: 'activity-filters', result: 'activity' },
      { path: '/dashboard/alerts', filters: 'alerts-filters', result: 'alerts' },
      { path: '/dashboard/new-in-release', filters: 'new-in-release-filters', result: 'new-in-release' },
    ] as const;

    for (const item of cases) {
      await expectAuthenticatedPage(page, item.path);
      await expect(page.locator(`[data-testid="${item.filters}"]`)).toBeVisible();
      await expect(
        page.locator(`[data-testid="${item.result}-empty"], [data-testid="${item.result}-results"]`).first(),
      ).toBeVisible();
    }
  });

  test('activity filters accept query params', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard/activity?q=login&page=1');
    await expect(page.locator('[data-testid="activity-filters"]')).toBeVisible();
  });
});
