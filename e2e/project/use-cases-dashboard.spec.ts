import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from '../support/helpers';

test.describe('Dashboard mentions — use cases', () => {
  test('mentions mark-all-read control is present when form exists (UC-DASH-06)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard/mentions');
    await dismissProductTour(page);
    await expect(page.getByRole('main')).toBeVisible();

    const markAll = page.locator('form[action*="/dashboard/mentions/read-all"] button[type="submit"]');
    if ((await markAll.count()) === 0) {
      // Empty feed — shell still OK.
      await expect(page.locator('[data-testid="mentions-empty"], [data-testid="mentions-results"], main').first()).toBeVisible();
      return;
    }
    await markAll.click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/dashboard\/mentions/);
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('assignments empty or results shell renders (UC-DASH-04)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard/assignments?status=unresolved');
    await expect(
      page.locator('[data-testid="assignments-empty"], [data-testid="assignments-results"], main').first(),
    ).toBeVisible();
  });
});
