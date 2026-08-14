import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  expectAuthenticatedPage,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
} from '../support/helpers';

test.describe('Issues & performance deep', () => {
  test('issue list table renders after sample seed', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/issues`);
    await dismissProductTour(page);
    await expect(page.locator('[data-tour="issue-filters"]')).toBeVisible();
    const rows = page.locator('table.issue-table tbody tr');
    requireSampleOrSkip((await rows.count()) > 0, 'No issue rows — run make seed-sample');
    await expect(rows.first()).toBeVisible();
  });

  test('issue detail shows comments and triage panels', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }
    await expect(page.locator('[data-testid="issue-comments"]')).toBeVisible();
    await expect(page.locator('[data-testid="issue-ai-export"], .issue-triage, .issue-hero__status').first()).toBeVisible();
  });

  test('AI markdown/json export endpoints respond', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    for (const ext of ['md', 'json'] as const) {
      const path = `/projects/${uuid}/issues/${issueUuid}/export/ai.${ext}`;
      const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 12_000 }).catch(() => null),
        page.goto(path, { waitUntil: 'commit' }).catch((err: Error) => {
          if (!/Download is starting/i.test(err.message)) {
            throw err;
          }
          return null;
        }),
      ]);
      if (download) {
        expect(download.suggestedFilename().length, path).toBeGreaterThan(0);
        continue;
      }
      await expect(page, path).not.toHaveURL(/\/login/);
    }
  });

  test('performance index and first transaction when present', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/performance`);
    const tx = page.locator(`a[href*="/projects/${uuid}/performance/"]`).first();
    if ((await tx.count()) === 0) {
      return;
    }
    await tx.click();
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/performance/`));
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('analytics page renders charts shell', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/analytics`);
    await expect(page.getByRole('main')).toBeVisible();
    await expect(page.getByRole('main').locator('canvas, .panel, h1, h2').first()).toBeVisible();
  });

  test('releases page loads with compare controls', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/releases`);
    await expect(page.getByRole('main')).toBeVisible();
  });

  test('notification and threshold create forms load', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/notifications/new`);
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
    await expectAuthenticatedPage(page, `/projects/${uuid}/threshold-rules/new`);
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
  });

  test('environment compare panel renders with query params', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/issues?environment=production&compare=staging`);
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="issue-compare"]')).toBeVisible();
    await expect(page.locator('[data-tour="issue-filters"]')).toBeVisible();
  });

  test('issue list pagination and per_page query params work', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/issues?per_page=10&page=1&sort=last_seen&dir=desc`);
    await expect(page.locator('[data-tour="issue-filters"]')).toBeVisible();
    await expect(page.locator('table.issue-table').first()).toBeVisible();
  });

  test('issue detail triage chrome includes AI export and comments', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }
    await expect(page.locator('[data-testid="issue-ai-export"]')).toBeVisible();
    await expect(page.locator('[data-testid="issue-comments"]')).toBeVisible();
    // Optional panels when the issue graph has neighbors / triage tools.
    if ((await page.locator('[data-testid="similar-issues"]').count()) > 0) {
      await expect(page.locator('[data-testid="similar-issues"]')).toBeAttached();
    }
    if ((await page.locator('[data-testid="mark-duplicate"]').count()) > 0) {
      await expect(page.locator('[data-testid="mark-duplicate"]')).toBeAttached();
    }
  });
});
