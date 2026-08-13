import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  expectAuthenticatedPage,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

test.describe('Issues workflow — use cases', () => {
  test('issue list accepts extended filter query params (UC-ISS-03)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    // Stick to params the filter form accepts without FULLTEXT edge cases.
    const qs = 'level=error&status=unresolved&environment=production&release=1.0.0&priority=high';
    await page.goto(`/projects/${uuid}/issues?${qs}`);
    await dismissProductTour(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('[data-tour="issue-filters"]')).toBeVisible();
    await expect(page).toHaveURL(/level=error/);
    await expect(page).toHaveURL(/environment=production/);
  });

  test('apply and delete a saved issue view (UC-ISS-08 / UC-ISS-09)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/issues?level=error&status=unresolved`);
    await dismissProductTour(page);

    const viewName = `e2e-apply-${Date.now().toString(36)}`;
    const nameInput = page.locator('input[name="issue_saved_view[name]"], #saved-view-name, #issue_saved_view_name');
    await expect(nameInput).toBeVisible({ timeout: 15_000 });
    await nameInput.fill(viewName);
    await page.locator('.issue-saved-views__save, form[action*="/issues/views"] button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('#saved-view-select')).toContainText(viewName, { timeout: 15_000 });

    const select = page.locator('#saved-view-select');
    const option = select.locator('option').filter({ hasText: viewName }).first();
    // Option value is the apply URL (navigate-select), not a bare UUID.
    const applyHref = await option.getAttribute('value');
    requireSampleOrSkip(!!applyHref, 'Saved view option value missing');
    if (!applyHref) {
      return;
    }

    await page.goto(applyHref);
    await dismissProductTour(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/issues`));
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('[data-tour="issue-filters"], .issue-saved-views').first()).toBeVisible();

    const viewUuid = applyHref.match(/\/views\/([0-9a-f-]{36})/i)?.[1];
    if (!viewUuid) {
      return;
    }
    const deleteForm = page.locator(`form[action*="/issues/views/${viewUuid}/delete"]`);
    if ((await deleteForm.count()) > 0) {
      await deleteForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
      await expect(page.locator('#saved-view-select')).not.toContainText(viewName, { timeout: 15_000 });
    }
  });

  test('ignore issue status when action is available (UC-ISS-15)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const ignoreForm = page.locator('form.issue-status-actions__form').filter({
      has: page.locator('input[name="status"][value="ignored"]'),
    });
    if (!(await ignoreForm.locator('button[type="submit"]').isVisible().catch(() => false))) {
      // Already ignored or UI gated — reopen first if possible.
      const unresolvedForm = page.locator('form.issue-status-actions__form').filter({
        has: page.locator('input[name="status"][value="unresolved"]'),
      });
      if (await unresolvedForm.locator('button[type="submit"]').isVisible().catch(() => false)) {
        await unresolvedForm.locator('button[type="submit"]').click();
        await waitForPageLoader(page);
      }
    }

    if (await ignoreForm.locator('button[type="submit"]').isVisible().catch(() => false)) {
      await ignoreForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
      await expect(page.locator('.issue-badge--status-ignored, .issue-badge--status')).toBeVisible({
        timeout: 15_000,
      });
    }
  });

  test('open first event detail from issue (UC-ISS-21)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const eventLink = page.locator(`a[href*="/projects/${uuid}/events/"]`).first();
    if ((await eventLink.count()) === 0) {
      requireSampleOrSkip(false, 'No event links on issue detail — run make seed-sample');
      return;
    }
    await eventLink.click();
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/events/`));
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.locator('.issue-hero')).toBeVisible();
  });

  test('performance N+1 filter query is accepted (UC-PERF-03)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/performance?nplus1=1`);
    await expect(page.getByRole('main')).toBeVisible();
    await expect(page).toHaveURL(/nplus1=1/);
  });

  test('mark-duplicate panel is present for triage users (UC-ISS-18)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }
    const panel = page.locator('[data-testid="mark-duplicate"]');
    // Demo admin is owner — panel should render even if no neighbors yet.
    await expect(panel).toBeAttached({ timeout: 10_000 });
  });
});
