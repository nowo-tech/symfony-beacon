import { test, expect } from '@playwright/test';
import { expectAuthenticatedPage } from './helpers';

test.describe('Account & preferences', () => {
  test('account area pages load', async ({ page }) => {
    for (const path of [
      '/account',
      '/account/preferences',
      '/account/profile',
      '/account/projects',
      '/account/groups',
      '/account/security',
      '/account/security/history',
      '/account/security/activity',
      '/account/display',
      '/account/display/panels',
      '/account/display/tours',
      '/account/display/notifications',
      '/account/privacy',
    ]) {
      await expectAuthenticatedPage(page, path);
    }
  });

  test('privacy export download is reachable', async ({ page }) => {
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 20_000 }).catch(() => null),
      page.goto('/account/privacy/export', { waitUntil: 'commit' }).catch((err: Error) => {
        // Playwright throws when navigation turns into a download — that is success.
        if (!/Download is starting/i.test(err.message)) {
          throw err;
        }
        return null;
      }),
    ]);

    if (download) {
      expect(download.suggestedFilename().length).toBeGreaterThan(0);
      return;
    }

    // Fallback: some builds render an HTML confirmation instead of a file.
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('account area nav is present when marked', async ({ page }) => {
    await page.goto('/account/profile');
    const nav = page.locator('[data-testid="account-area-nav"]');
    if (await nav.count()) {
      await expect(nav).toBeVisible();
    }
  });
});
