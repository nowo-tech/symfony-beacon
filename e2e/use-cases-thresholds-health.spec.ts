import { test, expect } from '@playwright/test';
import {
  beaconAuthHeader,
  dismissProductTour,
  ingestHttpBase,
  loadDemoIngestCredentials,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

test.describe('Thresholds, health, quiet hours, issue panels', () => {
  test('creates toggles and deletes a threshold rule (UC-NOTIF-07)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/threshold-rules/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.panel, form').first();
    await expect(form).toBeVisible();
    const label = `e2e-threshold-${Date.now().toString(36)}`;
    await form.locator('input[name="project_threshold_rule[label]"]').fill(label);
    await form.locator('input[name="project_threshold_rule[errorCount]"]').fill('5');
    await form.locator('input[name="project_threshold_rule[windowMinutes]"]').fill('15');
    await form.locator('input[name="project_threshold_rule[cooldownMinutes]"]').fill('30');
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/alerts`));
    await expect(page.getByRole('main')).toContainText(label, { timeout: 15_000 });

    const row = page.locator('li').filter({ hasText: label }).first();
    const toggle = row.locator('form[action*="/toggle"] button[type="submit"]').first();
    if (await toggle.isVisible().catch(() => false)) {
      await toggle.click();
      await waitForPageLoader(page);
    }
    const del = row.locator('form[action*="/delete"] button[type="submit"]').first();
    if (await del.isVisible().catch(() => false)) {
      page.once('dialog', (d) => d.accept().catch(() => undefined));
      await del.click({ force: true });
      await waitForPageLoader(page);
    }
  });

  test('saves quiet hours and digest on HTTP destination (UC-NOTIF-05)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible();

    const label = `e2e-quiet-${Date.now().toString(36)}`;
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('http');
    // Prefer a host that resolves reliably in Compose (example.com DNS is flaky → SSRF form error).
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill(
      'https://hooks.slack.com/services/T/E2E/QUIET',
    );

    const categories = form.locator('select[name="notification_destination[categories][]"]');
    if ((await categories.count()) > 0) {
      await categories.selectOption(['error'], { force: true }).catch(async () => {
        const ts = form.locator('.ts-control').first();
        if (await ts.isVisible().catch(() => false)) {
          await ts.click();
          await page.locator('.ts-dropdown .option').filter({ hasText: /error|error/i }).first().click({ force: true });
        }
      });
    }

    const quiet = form.locator('input[name="notification_destination[quietHoursEnabled]"]');
    await expect(quiet).toBeAttached();
    if (!(await quiet.isChecked().catch(() => false))) {
      await quiet.check();
    }
    const tz = form.locator('input[name="notification_destination[quietHoursTimezone]"]');
    await expect(tz).toBeVisible({ timeout: 5_000 });
    await tz.fill('UTC');
    const start = form.locator('input[name="notification_destination[quietHoursStart]"]');
    const end = form.locator('input[name="notification_destination[quietHoursEnd]"]');
    await expect(start).toBeVisible();
    await start.fill('22:00');
    await end.fill('07:00');
    const digest = form.locator('input[name="notification_destination[digestEnabled]"]');
    if ((await digest.count()) > 0 && !(await digest.isChecked().catch(() => false))) {
      await digest.check();
    }

    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    if (page.url().includes('/notifications/new')) {
      const errs = await page.locator('.form-error, .invalid-feedback, [class*="error"], li').allTextContents();
      throw new Error(`Quiet-hours destination did not save. Errors: ${errs.join(' | ').slice(0, 800)}`);
    }
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/alerts`), { timeout: 20_000 });
    await expect(page.getByRole('main')).toContainText(label, { timeout: 15_000 });

    const row = page.locator('#project-notification-destinations li').filter({ hasText: label }).first();
    await expect(row).toBeVisible();
    const edit = row.locator(`a[href*="/notifications/"][href*="/edit"]`);
    await edit.click();
    await waitForPageLoader(page);
    const editForm = page.getByRole('main').locator('form.notification-destination-form');
    await expect(editForm.locator('input[name="notification_destination[quietHoursEnabled]"]')).toBeChecked();
    await expect(editForm.locator('input[name="notification_destination[digestEnabled]"]')).toBeChecked();

    await page.goto(`/projects/${uuid}/settings/alerts`);
    await dismissProductTour(page);
    const del = page
      .locator('#project-notification-destinations li')
      .filter({ hasText: label })
      .locator('form[action*="/delete"] button[type="submit"]')
      .first();
    if (await del.isVisible().catch(() => false)) {
      page.once('dialog', (d) => d.accept().catch(() => undefined));
      await del.click({ force: true });
      await waitForPageLoader(page);
    }
  });

  test('project health / delivery panel on alerts settings (UC-PROJ-21)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedHealth(page, uuid);
  });

  test('issue detail exposes history and payload panels (UC-ISS-22 / UC-ISS-23)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }
    await expect(page.locator('.issue-history, [data-collapse-panel-id-value="activity"]').first()).toBeAttached();
    // Expand activity / details if collapsed.
    for (const id of ['activity', 'details', 'similar', 'duplicate']) {
      const btn = page.locator(`[data-collapse-panel-id-value="${id}"] [data-collapse-panel-target="button"]`).first();
      if ((await btn.count()) > 0 && (await btn.getAttribute('aria-expanded')) === 'false') {
        await btn.click();
      }
    }
    await expect(page.locator('.issue-history__list, .issue-history__empty, [data-testid="similar-issues"], [data-testid="mark-duplicate"]').first()).toBeAttached();
  });

  test('mark-duplicate form can be opened (UC-ISS-18)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }
    const panel = page.locator('[data-testid="mark-duplicate"]');
    await expect(panel).toBeAttached();
    const open = panel.locator('button[data-action="confirm-dialog#open"]').first();
    if (await open.isVisible().catch(() => false)) {
      await open.click();
      await expect(page.locator('dialog[open], .confirm-dialog[open], form').filter({ hasText: /duplicate|duplicad/i }).first()).toBeVisible();
      await page.keyboard.press('Escape').catch(() => undefined);
    }
  });
});

async function expectAuthenticatedHealth(page: import('@playwright/test').Page, uuid: string): Promise<void> {
  await page.goto(`/projects/${uuid}/settings/alerts`);
  await dismissProductTour(page);
  await expect(page.getByRole('main')).toContainText(/health|salud|delivery|entrega|messenger/i);
}

test.describe('Ingest suspend gate (UC-ING-08)', () => {
  test('suspended ingest rejects envelope (UC-ING-08)', async ({ page, request }) => {
    const creds = loadDemoIngestCredentials();
    if (!creds) {
      requireSampleOrSkip(false, '.demo-client.env missing — run make seed');
      return;
    }

    // Prefer demo UUID (avoid clicking /admin/projects/new from the list).
    const uuid = creds.projectUuid ?? (await resolveDemoProjectUuid(page));
    await page.goto(`/admin/projects/${uuid}`);
    await dismissProductTour(page);
    await waitForPageLoader(page);
    await expect(page.locator('[data-testid="admin-project-show"]')).toBeVisible();

    const suspendBtn = page.getByRole('button', { name: /suspend|suspender|unterbrechen|opschorten|suspendre|sospendere/i });
    const resumeBtn = page.getByRole('button', { name: /resume|reanudar|wieder|hervat|reprendre|riprendi|retomar/i });

    if (await suspendBtn.isVisible().catch(() => false)) {
      await suspendBtn.click();
      await waitForPageLoader(page);
    } else if (!(await resumeBtn.isVisible().catch(() => false))) {
      requireSampleOrSkip(false, 'Ingest toggle controls missing on admin project show');
      return;
    }

    await expect(resumeBtn).toBeVisible({ timeout: 15_000 });

    const base = ingestHttpBase();
    const denied = await request.post(`${base}/api/${creds.projectId}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(creds.publicKey, creds.secretKey),
      },
      data: '{}\n{}\n{}',
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect([403, 429], await denied.text()).toContain(denied.status());

    // Resume ingest so other suites keep working.
    await resumeBtn.click();
    await waitForPageLoader(page);
    await expect(suspendBtn).toBeVisible({ timeout: 15_000 });
  });
});
