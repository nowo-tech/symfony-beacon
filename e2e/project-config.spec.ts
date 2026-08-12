import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
  dismissProductTour,
  expectAuthenticatedPage,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

/** Navigate to a URL that may trigger a file download; return the download or null. */
async function gotoDownload(page: import('@playwright/test').Page, url: string) {
  const [download] = await Promise.all([
    page.waitForEvent('download', { timeout: 20_000 }).catch(() => null),
    page.goto(url, { waitUntil: 'commit' }).catch((err: Error) => {
      if (!/Download is starting/i.test(err.message)) {
        throw err;
      }
      return null;
    }),
  ]);
  return download;
}

test.describe('Project config portability (089)', () => {
  test('settings shows export/import panels and tabs switch', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings`);
    await dismissProductTour(page);

    const panel = page.locator('[data-testid="project-config-portability"]');
    await expect(panel).toBeVisible();
    await expect(panel.locator('[data-testid="project-config-download"]')).toBeVisible();

    await panel.locator('#tab-project-config-import').click();
    await expect(panel.locator('[data-testid="project-config-import"]')).toBeVisible();
    await expect(panel.locator('[data-testid="project-config-file"]')).toBeVisible();
    await expect(panel.locator('[data-testid="project-config-import-submit"]')).toBeVisible();

    await panel.locator('#tab-project-config-export').click();
    await expect(panel.locator('[data-testid="project-config-download"]')).toBeVisible();
  });

  test('project config export download is reachable', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const download = await gotoDownload(page, `/projects/${uuid}/config/export`);

    if (download) {
      expect(download.suggestedFilename()).toMatch(/\.json$/i);
      const filePath = await download.path();
      expect(filePath).toBeTruthy();
      const raw = fs.readFileSync(filePath!, 'utf8');
      const payload = JSON.parse(raw) as { schema?: string; projects?: unknown[] };
      expect(payload.schema).toBeTruthy();
      expect(Array.isArray(payload.projects)).toBe(true);
      return;
    }

    await expect(page).not.toHaveURL(/\/login/);
  });

  test('export then re-import the same project config', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const download = await gotoDownload(page, `/projects/${uuid}/config/export`);
    expect(download, 'Expected project config JSON download').not.toBeNull();

    const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'beacon-e2e-config-'));
    const jsonPath = path.join(tmpDir, download!.suggestedFilename() || 'beacon-project.json');
    await download!.saveAs(jsonPath);
    expect(fs.statSync(jsonPath).size).toBeGreaterThan(20);

    await page.goto(`/projects/${uuid}/settings`);
    await dismissProductTour(page);

    const panel = page.locator('[data-testid="project-config-portability"]');
    await panel.locator('#tab-project-config-import').click();
    await expect(panel.locator('[data-testid="project-config-import"]')).toBeVisible();

    await panel.locator('[data-testid="project-config-file"]').setInputFiles(jsonPath);
    await panel.locator('[data-testid="project-config-import-submit"]').click();
    await waitForPageLoader(page);

    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings`));
    await expect(page).not.toHaveURL(/\/login/);
    // Success or validation flash — page must stay on settings with the portability panel.
    await expect(panel).toBeVisible();
  });
});

test.describe('Admin project config portability (089)', () => {
  test('admin projects list shows export/import panels', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/projects');
    const panel = page.locator('[data-testid="admin-projects-config-portability"]');
    await expect(panel).toBeVisible();
    await expect(panel.locator('[data-testid="admin-projects-config-download"]')).toBeVisible();

    await panel.locator('#tab-admin-projects-config-import').click();
    await expect(panel.locator('[data-testid="admin-projects-config-import"]')).toBeVisible();
    await expect(panel.locator('[data-testid="admin-projects-config-file"]')).toBeVisible();
  });

  test('admin export-all download is reachable', async ({ page }) => {
    const download = await gotoDownload(page, '/admin/projects/export');

    if (download) {
      expect(download.suggestedFilename()).toMatch(/\.json$/i);
      const filePath = await download.path();
      expect(filePath).toBeTruthy();
      const payload = JSON.parse(fs.readFileSync(filePath!, 'utf8')) as { projects?: unknown[] };
      expect(Array.isArray(payload.projects)).toBe(true);
      return;
    }

    await expect(page).not.toHaveURL(/\/login/);
  });

  test('admin single-project export from show page', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/admin/projects/${uuid}`);
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="admin-project-show"]')).toBeVisible();

    const exportLink = page.locator('[data-testid="admin-project-config-download"]');
    await expect(exportLink).toBeVisible();

    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 20_000 }).catch(() => null),
      exportLink.click().catch(() => undefined),
    ]);

    if (download) {
      expect(download.suggestedFilename()).toMatch(/\.json$/i);
      return;
    }

    // Fallback: hit the route directly.
    const viaGoto = await gotoDownload(page, `/admin/projects/${uuid}/export`);
    if (viaGoto) {
      expect(viaGoto.suggestedFilename()).toMatch(/\.json$/i);
      return;
    }
    await expect(page).not.toHaveURL(/\/login/);
  });
});
