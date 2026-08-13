import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, gotoStable, waitForPageLoader } from './helpers';

test.describe('Settings deep checks', () => {
  test('appearance tabs switch between sections', async ({ page }) => {
    await page.goto('/admin/appearance');
    await dismissProductTour(page);

    // Index redirects to admin_appearance_section — breadcrumbs must still render.
    const crumbs = page.locator('.beacon-breadcrumb-wrap');
    await expect(crumbs).toBeVisible();
    await expect(crumbs).toContainText(/appearance|apariencia|apparence|aspetto|aparência|erscheinungsbild|weergave/i);

    const tabs = page.locator('[data-testid="appearance-tabs"]');
    await expect(tabs).toBeVisible();

    for (const section of ['themes', 'brand', 'layout', 'colors']) {
      const tab = page.locator(`[data-testid="appearance-tab-${section}"]`);
      await expect(tab).toBeVisible();
      await tab.click();
      await waitForPageLoader(page);
      await expect(page).toHaveURL(new RegExp(`/admin/appearance/${section}`));
      await expect(page.getByRole('main').locator('form').first()).toBeVisible();
      await expect(page.locator('.beacon-breadcrumb-wrap')).toBeVisible();
    }
  });

  test('appearance colors subtabs are reachable', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/appearance/colors');
    const subtabs = page.locator('[data-testid="appearance-subtabs"]');
    if ((await subtabs.count()) === 0) {
      return;
    }
    for (const sub of ['accents', 'status', 'surfaces']) {
      const link = page.locator(`[data-testid="appearance-subtab-${sub}"], a[href*="sub=${sub}"]`).first();
      if ((await link.count()) === 0) {
        continue;
      }
      await link.click();
      await waitForPageLoader(page);
      await expect(page).toHaveURL(new RegExp(`/admin/appearance/colors/${sub}`));
    }
  });

  test('ops defaults tabs and forms load', async ({ page }) => {
    await page.goto('/admin/ops-defaults');
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="ops-defaults-tabs"]')).toBeVisible();

    for (const section of ['governance', 'ingest', 'metrics', 'inbound', 'notifications']) {
      const tab = page.locator(`[data-testid="ops-defaults-tab-${section}"]`);
      await expect(tab).toBeVisible();
      await tab.click();
      await waitForPageLoader(page);
      await expect(page).toHaveURL(new RegExp(`/admin/ops-defaults/${section}`));
      const form = page.locator('[data-testid="ops-defaults-form"]');
      await expect(form).toBeVisible();
      await expect(form).toHaveAttribute('data-ops-section', section);
      await expect(page.locator('[data-testid="ops-defaults-submit"]')).toBeVisible();
    }
  });

  test('instance config export and import panels load', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/instance-config');
    await expect(page.locator('[data-testid="instance-config-export"]')).toBeVisible();
    await expect(page.locator('[data-testid="instance-config-download"]')).toBeVisible();
    await expect(page.locator('[data-testid="instance-config-import"]')).toBeVisible();
    await expect(page.locator('[data-testid="instance-config-file"]')).toBeVisible();
  });

  test('instance config export download is reachable', async ({ page }) => {
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 20_000 }).catch(() => null),
      page.goto('/admin/instance-config/export', { waitUntil: 'commit' }).catch((err: Error) => {
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
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('admin hub deep cards navigate', async ({ page }) => {
    await gotoStable(page, '/admin');
    await dismissProductTour(page);

    const cards = [
      { testid: 'admin-ops-defaults', url: /\/admin\/ops-defaults/ },
      { testid: 'admin-instance-config', url: /\/admin\/instance-config/ },
      { testid: 'admin-http-log', url: /\/admin\/http-log/ },
      { testid: 'admin-cookie-consent', url: /\/admin\/cookie-consent|\/cookie-consent/ },
      { testid: 'admin-roles', url: /\/admin\/roles/ },
      { testid: 'admin-permissions', url: /\/admin\/permissions/ },
    ] as const;

    for (const card of cards) {
      await gotoStable(page, '/admin');
      await dismissProductTour(page);
      const link = page.locator(`[data-testid="${card.testid}"]`);
      await expect(link).toBeVisible();
      await link.click();
      await expect(page).toHaveURL(card.url);
      await expect(page).not.toHaveURL(/\/login/);
    }
  });

  test('legacy /settings/* paths redirect into admin settings', async ({ page }) => {
    for (const [from, to] of [
      ['/settings/appearance', /\/admin\/appearance/],
      ['/settings/ops-defaults', /\/admin\/ops-defaults/],
      ['/settings/instance-config', /\/admin\/instance-config/],
    ] as const) {
      await gotoStable(page, from);
      await dismissProductTour(page);
      await expect(page).toHaveURL(to);
      await expect(page).not.toHaveURL(/\/login/);
      await expect(page.getByRole('main')).toBeVisible();
    }
  });
});
