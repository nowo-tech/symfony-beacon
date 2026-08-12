import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  expectAuthenticatedPage,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

test.describe('Admin project show deep', () => {
  test('list links into project show with stats and members', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto('/admin/projects');
    await dismissProductTour(page);

    const rowLink = page.locator(`a[href="/admin/projects/${uuid}"], a[href*="/admin/projects/${uuid}"]`).first();
    if ((await rowLink.count()) > 0) {
      await rowLink.click();
    } else {
      await page.goto(`/admin/projects/${uuid}`);
    }
    await dismissProductTour(page);

    await expect(page).toHaveURL(new RegExp(`/admin/projects/${uuid}`));
    await expect(page.locator('[data-testid="admin-project-show"]')).toBeVisible();
    await expect(page.locator('[data-testid="admin-project-stats"]')).toBeVisible();
    await expect(page.getByRole('main')).toContainText(/member|miembro|owner|propietario|role|rol/i);
  });

  test('edit form loads from show page', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/admin/projects/${uuid}`);
    const edit = page.locator(`a[href*="/admin/projects/${uuid}/edit"]`).first();
    await expect(edit).toBeVisible();
    await edit.click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/admin/projects/${uuid}/edit`));
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
  });

  test('open settings and issues shortcuts from admin show', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/admin/projects/${uuid}`);
    await dismissProductTour(page);

    await page.locator(`a[href*="/projects/${uuid}/settings"]`).first().click();
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings`));
    await expect(page).not.toHaveURL(/\/login/);

    await page.goto(`/admin/projects/${uuid}`);
    await dismissProductTour(page);
    await page.locator(`a[href*="/projects/${uuid}/issues"]`).first().click();
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/issues`));
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('audit timeline filter shell is present', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/admin/projects/${uuid}`);
    const timeline = page.locator('#project-audit-timeline, [data-testid="project-audit-entry"], form').filter({
      has: page.locator('select, input[type="date"], input[name*="action"]'),
    });
    // Audit block always renders; entries depend on history.
    await expect(page.getByRole('main')).toContainText(/audit|historial|activity|actividad/i);
    if ((await page.locator('[data-testid="project-audit-entry"]').count()) > 0) {
      await expect(page.locator('[data-testid="project-audit-entry"]').first()).toBeVisible();
    }
    await expect(timeline.first()).toBeAttached();
  });
});
