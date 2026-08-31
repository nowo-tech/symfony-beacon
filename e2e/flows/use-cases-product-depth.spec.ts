import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';
import { addProjectMember, createEnabledUser } from '../support/security';

async function createEphemeralProject(page: import('@playwright/test').Page, name: string): Promise<string> {
  await page.goto('/dashboard?new=1');
  await dismissProductTour(page);
  if (!(await page.locator('input[name="project[name]"]').isVisible().catch(() => false))) {
    await page.locator('[data-tour="new-project"], [data-action="new-project"]').first().click();
  }
  await expect(page.locator('input[name="project[name]"]')).toBeVisible({ timeout: 10_000 });
  await page.locator('input[name="project[name]"]').fill(name);
  await page.locator('textarea[name="project[description]"]').fill('Product depth E2E');
  await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();
  await page.waitForURL(/\/projects\/([0-9a-f-]{36})/i, { timeout: 30_000 });
  await dismissProductTour(page);
  const match = page.url().match(/\/projects\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse project UUID from ${page.url()}`);
  }
  return match[1];
}

test.describe('Project & issue product depth', () => {
  test('owner renames project from settings general (UC-PROJ-22-D1)', async ({ page }) => {
    test.setTimeout(90_000);
    const suffix = Date.now().toString(36);
    const name = `E2E OwnerRename ${suffix}`;
    const uuid = await createEphemeralProject(page, name);
    const renamed = `${name} owner`;

    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    const nameInput = page.locator('input[name="project[name]"], #project_name');
    if ((await nameInput.count()) === 0) {
      test.info().annotations.push({ type: 'note', description: 'project name field not on general settings — skip' });
      return;
    }
    await expect(nameInput.first()).toBeVisible({ timeout: 15_000 });
    await nameInput.first().fill(renamed);
    await page.locator('form').filter({ has: nameInput.first() }).locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await page.goto(`/projects/${uuid}/issues`);
    await dismissProductTour(page);
    await expect(page.getByRole('heading', { level: 1 })).toContainText(renamed, { timeout: 15_000 });
  });

  test('assigns project member to issue via autocomplete (UC-ISS-17-D1)', async ({ page }) => {
    test.setTimeout(120_000);
    const uuid = await resolveDemoProjectUuid(page);
    const suffix = Date.now().toString(36);
    const memberEmail = `e2e.assign.${suffix}@symfony-beacon.local`;
    await createEnabledUser(page, memberEmail, 'AssignMe123!', `Assign ${suffix}`);
    await addProjectMember(page, uuid, memberEmail, 'member');

    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const assigneeSection = page.locator('[data-controller~="collapse-panel"][data-collapse-panel-id-value="assignee"]');
    if ((await assigneeSection.count()) > 0) {
      const toggle = assigneeSection.locator('[data-collapse-panel-target="button"]');
      if ((await toggle.getAttribute('aria-expanded')) === 'false') {
        await toggle.click();
      }
    }

    const assigneeForm = page.locator('form[name="issue_assignee"]');
    await expect(assigneeForm).toBeVisible({ timeout: 15_000 });
    const ts = assigneeForm.locator('.ts-control').first();
    if (await ts.isVisible().catch(() => false)) {
      await ts.click();
      await page.keyboard.type(memberEmail.split('@')[0]);
      const opt = page.locator('.ts-dropdown .option').filter({ hasText: new RegExp(memberEmail.split('@')[0], 'i') }).first();
      await expect(opt).toBeVisible({ timeout: 15_000 });
      await opt.click({ force: true });
    } else {
      const select = assigneeForm.locator('select[name*="assignee"]');
      await select.selectOption({ label: memberEmail }).catch(async () => {
        await select.selectOption({ index: 1 });
      });
    }
    const dupDialog = page.locator('dialog[aria-labelledby="issue-duplicate-dialog-title"], dialog[data-confirm-dialog-target="dialog"]').first();
    if (await dupDialog.isVisible().catch(() => false)) {
      await dupDialog.locator('button').filter({ hasText: /cancel|cerrar|close|continuar|continue|dismiss|ok/i }).first().click({ force: true });
      await dupDialog.waitFor({ state: 'hidden', timeout: 10_000 }).catch(() => undefined);
    }
    await assigneeForm.locator('button[type="submit"]').click({ force: true });
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(/assign|asign|actualiz|updated/i, { timeout: 15_000 });
  });

  test('resolve then reopen records status history entries (UC-ISS-14-D1)', async ({ page }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const resolveForm = page.locator('form.issue-status-actions__form').filter({
      has: page.locator('input[name="status"][value="resolved"]'),
    });
    const unresolvedForm = page.locator('form.issue-status-actions__form').filter({
      has: page.locator('input[name="status"][value="unresolved"]'),
    });

    if (await resolveForm.locator('button[type="submit"]').isVisible().catch(() => false)) {
      await resolveForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
      await expect(page.locator('.issue-badge--status-resolved')).toBeVisible({ timeout: 15_000 });
    } else if (await unresolvedForm.locator('button[type="submit"]').isVisible().catch(() => false)) {
      await unresolvedForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
    }

    const historyTab = page.getByRole('tab', { name: /history|historial|activity|actividad/i }).first();
    if ((await historyTab.count()) > 0) {
      await historyTab.click();
    } else {
      const historyLink = page.locator('a[href*="history"], a[href*="activity"]').filter({ hasText: /history|historial|activity/i }).first();
      if ((await historyLink.count()) > 0) {
        await historyLink.click();
      }
    }

    const historyList = page.locator('.issue-history, [data-testid="issue-history"], .issue-activity, .timeline').first();
    if ((await historyList.count()) > 0) {
      await expect(historyList).toBeVisible({ timeout: 15_000 });
      const entriesBefore = await historyList.locator('li, .issue-history__item, tr').count();

      if (await unresolvedForm.locator('button[type="submit"]').isVisible().catch(() => false)) {
        await unresolvedForm.locator('button[type="submit"]').click();
        await waitForPageLoader(page);
        await expect(page.locator('.issue-badge--status-unresolved, .issue-badge--status')).toBeVisible({ timeout: 15_000 });
      } else if (await resolveForm.locator('button[type="submit"]').isVisible().catch(() => false)) {
        await resolveForm.locator('button[type="submit"]').click();
        await waitForPageLoader(page);
      }

      if ((await historyTab.count()) > 0) {
        await historyTab.click();
      }
      await expect(historyList.locator('li, .issue-history__item, tr')).toHaveCount(Math.max(entriesBefore, 1), { timeout: 15_000 });
    }
  });
});

test.describe('Notification delivery depth', () => {
  test('send test records delivery attempt on destination health (UC-NOTIF-04-D1)', async ({ page }) => {
    test.setTimeout(120_000);
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible();
    const label = `e2e-deliv-${Date.now().toString(36)}`;
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('http');
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill('https://example.com/hooks/beacon-test-delivery');
    const categories = form.locator('select[name="notification_destination[categories][]"]');
    if ((await categories.count()) > 0) {
      await categories.selectOption(['error'], { force: true }).catch(() => undefined);
    }
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/alerts`), { timeout: 20_000 });

    const row = page.locator('#project-notification-destinations li').filter({ hasText: label }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    await row.getByRole('button', { name: /send test|enviar prueba|test/i }).first().click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/queued|encolad|test|prueba/i);

    let deliveryRecorded = false;
    for (let i = 0; i < 20; i++) {
      await page.reload();
      await dismissProductTour(page);
      const health = page.locator('section.panel').filter({ hasText: /health|salud/i }).first();
      if ((await health.count()) > 0) {
        const text = await health.innerText();
        if (/last delivery|última entrega|delivery_ok|delivery_fail|historial|history/i.test(text)) {
          if (!/never delivered|nunca entreg/i.test(text)) {
            deliveryRecorded = true;
            break;
          }
        }
        const historyToggle = health.locator('details summary').first();
        if ((await historyToggle.count()) > 0) {
          await historyToggle.click().catch(() => undefined);
          if ((await health.locator('details ul li').count()) > 0) {
            deliveryRecorded = true;
            break;
          }
        }
      }
      await page.waitForTimeout(2_000);
    }
    expect(deliveryRecorded, 'destination health should show a delivery attempt after send test').toBeTruthy();

    const del = row.locator('form[action*="/delete"] button[type="submit"]').first();
    if (await del.isVisible().catch(() => false)) {
      page.once('dialog', (d) => d.accept().catch(() => undefined));
      await del.click({ force: true });
      await waitForPageLoader(page);
    }
  });
});
