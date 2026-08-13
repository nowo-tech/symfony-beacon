import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, resolveDemoProjectUuid, waitForPageLoader } from './helpers';

test.describe('Project settings deep', () => {
  test('settings shows governance, members, tokens, and share panels', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    await expect(page.locator('#project_governance_retention_days')).toBeVisible();

    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="read-api-tokens"]')).toBeVisible();
    await expect(page.locator('[data-testid="share-links"]')).toBeVisible();
    // Members list is always present for owners/admins of the demo project.
    await expect(page.getByRole('main')).toContainText(/member|miembro|role|rol/i);
  });

  test('API key DSN is visible or redacted for seeded project', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);

    const dsn = page.locator('[data-testid="api-key-dsn"], [data-testid="api-key-dsn-redacted"]');
    if ((await dsn.count()) === 0) {
      const createForm = page.locator(`form[action*="/projects/${uuid}/keys"]`);
      await expect(createForm).toBeVisible();
      await createForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
    }
    await expect(page.locator('[data-testid="api-key-dsn"], [data-testid="api-key-dsn-redacted"]').first()).toBeVisible({
      timeout: 15_000,
    });
  });

  test('notification help route loads', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/notifications/help`);
    await expect(page.getByRole('main')).toBeVisible();
  });

  test('releases compare query params are accepted', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/releases?from=&to=`);
    await expect(page.getByRole('main')).toBeVisible();
  });

  test('config portability panel is present for managers', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/data`);
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="project-config-portability"]')).toBeVisible();
    await expect(page.locator('[data-testid="project-config-download"]')).toBeVisible();
  });

  test('danger zone section is present for owners', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/danger`);
    await dismissProductTour(page);
    await expect(page.locator('.panel--danger, section.panel').filter({ hasText: /danger|peligro|delete|eliminar/i }).first()).toBeVisible();
  });

  test('notification destinations list shell loads', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/settings/alerts`);
    await expect(page.getByRole('main')).toContainText(/notification|notificaci/i);
  });
});

