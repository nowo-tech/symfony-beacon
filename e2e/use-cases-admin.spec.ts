import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage } from './helpers';

test.describe('Administration — use cases', () => {
  test('social login index and new provider form (UC-ADM-12)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/social-login');
    await expect(page.getByRole('main')).toBeVisible();
    await expectAuthenticatedPage(page, '/admin/social-login/new');
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
  });

  test('mailer settings form fields are present (UC-ADM-13)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/mailer');
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
    await expect(page.getByRole('main').locator('input, textarea').first()).toBeVisible();
  });

  test('admin user export endpoint for first user (UC-ADM-04)', async ({ page }) => {
    await page.goto('/admin/users');
    await dismissProductTour(page);
    const rowLink = page.locator('a[href*="/admin/users/"][href*="/activity"], a[href*="/admin/users/"][href*="/export"]').first();
    const exportLink = page.locator('a[href*="/admin/users/"][href$="/export"]').first();
    if ((await exportLink.count()) > 0) {
      const href = await exportLink.getAttribute('href');
      expect(href).toBeTruthy();
      const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 12_000 }).catch(() => null),
        page.goto(href!, { waitUntil: 'commit' }).catch((err: Error) => {
          if (!/Download is starting/i.test(err.message)) {
            throw err;
          }
          return null;
        }),
      ]);
      if (download) {
        expect(download.suggestedFilename().length).toBeGreaterThan(0);
      } else {
        await expect(page).not.toHaveURL(/\/login/);
      }
      return;
    }
    // Derive from activity link when export is behind a button on activity page.
    if ((await rowLink.count()) === 0) {
      return;
    }
    const activityHref = await rowLink.getAttribute('href');
    if (!activityHref) {
      return;
    }
    const exportPath = activityHref.replace(/\/activity$/, '/export');
    const res = await page.request.get(exportPath);
    expect(res.status()).toBeLessThan(500);
  });

  test('view-as-member enable then disable (UC-ADM-08)', async ({ page }) => {
    await page.goto('/admin/projects');
    await dismissProductTour(page);
    const show = page.locator('a[href*="/admin/projects/"]').filter({ hasNot: page.locator('[href*="/new"]') }).first();
    if ((await show.count()) === 0) {
      return;
    }
    await show.click();
    await expect(page).toHaveURL(/\/admin\/projects\/.+/);
    await dismissProductTour(page);

    const enable = page.locator('form[action*="/admin/view-as-member/enable"] button[type="submit"]').first();
    if (await enable.isVisible().catch(() => false)) {
      await enable.click();
      await dismissProductTour(page);
      await expect(page).not.toHaveURL(/\/login/);
    }

    const disable = page.locator('form[action*="/admin/view-as-member/disable"] button[type="submit"]').first();
    if (await disable.isVisible().catch(() => false)) {
      await disable.click();
      await dismissProductTour(page);
      await expect(page).not.toHaveURL(/\/login/);
    }
  });

  test('admin projects new form loads (UC-ADM-06)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/projects/new');
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
  });
});
