import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  expectAuthenticatedPage,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

test.describe('Mutations — project create', () => {
  test('creates a project from the dashboard dialog', async ({ page }) => {
    await page.goto('/dashboard?new=1');
    await dismissProductTour(page);

    const dialog = page.locator('dialog[open], dialog.confirm-dialog[open], dialog:not([hidden])').first();
    // ?new=1 should open the dialog; fall back to the tour button.
    if (!(await dialog.isVisible().catch(() => false))) {
      await page.locator('[data-tour="new-project"]').click();
    }
    await expect(page.locator('input[name="project[name]"]')).toBeVisible({ timeout: 10_000 });

    const suffix = Date.now().toString(36);
    const name = `E2E Project ${suffix}`;
    await page.locator('input[name="project[name]"]').fill(name);
    await page.locator('textarea[name="project[description]"]').fill('Created by Playwright E2E');
    await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();

    await page.waitForURL(/\/projects\/[0-9a-f-]{36}/i, { timeout: 30_000 });
    await dismissProductTour(page);
    await expect(page.getByRole('heading', { level: 1 })).toContainText(name);
  });
});

test.describe('Mutations — issue triage', () => {
  test('filters issues by level and status via the filter form', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/issues`);
    await dismissProductTour(page);

    const filters = page.locator('form.issue-filters, [data-tour="issue-filters"]');
    await expect(filters).toBeVisible();
    await filters.locator('select[name="level"]').selectOption('error');
    await filters.locator('select[name="status"]').selectOption('unresolved');
    await filters.locator('button.issue-filters__submit').click();
    await page.waitForURL(/[?&]level=error/);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('[data-tour="issue-filters"]')).toBeVisible();
    await expect(page.locator('table.issue-table').first()).toBeVisible();
  });

  test('saves a named issue view', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/issues?level=error&status=unresolved`);
    await dismissProductTour(page);

    const viewName = `e2e-view-${Date.now().toString(36)}`;
    // Symfony FormKit field id is issue_saved_view_name (not the old #saved-view-name).
    const nameInput = page.locator('input[name="issue_saved_view[name]"], #saved-view-name, #issue_saved_view_name');
    await expect(nameInput).toBeVisible({ timeout: 15_000 });
    await nameInput.fill(viewName);
    await page.locator('.issue-saved-views__save, form[action*="/issues/views"] button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    // <option> nodes are not "visible"; assert via select text / delete chip.
    await expect(page.locator('#saved-view-select')).toContainText(viewName, { timeout: 15_000 });
  });

  test('adds a comment and toggles issue status', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues available — run make seed-sample');
      return;
    }

    const comment = `E2E comment ${Date.now().toString(36)}`;
    const commentForm = page.locator('[data-testid="issue-comments"] form, form.issue-comments__form');
    await expect(commentForm).toBeVisible();
    const body = commentForm.locator('textarea, input[name="body"], textarea[name="body"]').first();
    await body.fill(comment);
    await commentForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('[data-testid="issue-comments"]')).toContainText(comment, { timeout: 15_000 });

    // Toggle status via hidden input value (locale-independent).
    const resolveForm = page.locator('form.issue-status-actions__form').filter({
      has: page.locator('input[name="status"][value="resolved"]'),
    });
    const unresolvedForm = page.locator('form.issue-status-actions__form').filter({
      has: page.locator('input[name="status"][value="unresolved"]'),
    });

    if (await resolveForm.locator('button[type="submit"]').isVisible().catch(() => false)) {
      await resolveForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
      await expect(page.locator('.issue-badge--status-resolved, .issue-badge--status')).toBeVisible({
        timeout: 15_000,
      });
      await expect(page.locator('.issue-badge--status-resolved')).toBeVisible();
    } else if (await unresolvedForm.locator('button[type="submit"]').isVisible().catch(() => false)) {
      await unresolvedForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
      await expect(page.locator('.issue-badge--status-unresolved')).toBeVisible({ timeout: 15_000 });
    }

    // AI export links
    await expect(page.locator('[data-testid="issue-ai-export"]')).toBeVisible();
    const md = page.locator('[data-testid="issue-ai-export"] a[href*="/export/ai.md"]').first();
    if (await md.count()) {
      const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 10_000 }).catch(() => null),
        md.click().catch(() => undefined),
      ]);
      if (download) {
        expect(download.suggestedFilename().length).toBeGreaterThan(0);
      }
    }
  });

  test('updates issue priority', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues available — run make seed-sample');
      return;
    }

    const priorityForm = page.locator('form.issue-priority-form');
    if ((await priorityForm.count()) === 0) {
      requireSampleOrSkip(false, 'Priority form not visible (collapsed panel or permissions)');
      return;
    }
    // Expand triage panel when collapse-panel left it closed (localStorage / defaults).
    const triageSection = page.locator(
      '[data-controller~="collapse-panel"][data-collapse-panel-id-value="triage"]',
    );
    if ((await triageSection.count()) > 0) {
      const toggle = triageSection.locator('[data-collapse-panel-target="button"]');
      if ((await toggle.getAttribute('aria-expanded')) === 'false') {
        await toggle.click();
      }
    }
    // FormKit names the field issue_priority[priority] (not bare "priority").
    await priorityForm
      .locator('select[name="issue_priority[priority]"], select[name="priority"]')
      .selectOption({ index: 1 });
    await priorityForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/issues/${issueUuid}`));
    await expect(page).not.toHaveURL(/\/login/);
  });
});

test.describe('Mutations — project settings', () => {
  test('creates a share link and a read API token', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings`);
    await dismissProductTour(page);

    const share = page.locator('[data-testid="share-links"]');
    await expect(share).toBeVisible();
    await share.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('[data-testid="share-url"]')).toBeVisible({ timeout: 15_000 });

    const tokens = page.locator('[data-testid="read-api-tokens"]');
    await expect(tokens).toBeVisible();
    await tokens.locator('input[name="label"]').fill(`e2e-token-${Date.now().toString(36)}`);
    await tokens.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('[data-testid="read-token-secret"], [data-testid="read-token-row"]').first()).toBeVisible({
      timeout: 15_000,
    });
  });

  test('saves governance retention fields', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings`);
    await dismissProductTour(page);

    const retention = page.locator('#retention_days');
    await expect(retention).toBeVisible();
    await retention.fill('30');
    await page.locator('form').filter({ has: retention }).locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings`));
    await expect(page.locator('#retention_days')).toHaveValue('30');
  });
});

test.describe('Mutations — admin', () => {
  test('creates an admin group', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/groups/new');
    const suffix = Date.now().toString(36);
    const name = `E2E Group ${suffix}`;
    const form = page.locator('form').filter({ has: page.locator('input[name="admin_group[name]"]') });
    await form.locator('input[name="admin_group[name]"]').fill(name);
    await form.locator('textarea[name="admin_group[description]"]').fill('Playwright group');
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.getByRole('main')).toContainText(name, { timeout: 15_000 });
  });
});
