import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

async function fillCategories(page: import('@playwright/test').Page, form: import('@playwright/test').Locator): Promise<void> {
  const categories = form.locator('select[name="notification_destination[categories][]"]');
  if ((await categories.count()) === 0) {
    return;
  }
  await categories.selectOption(['error'], { force: true }).catch(async () => {
    const ts = form.locator('.ts-control').first();
    if (await ts.isVisible().catch(() => false)) {
      await ts.click();
      await page.locator('.ts-dropdown .option').filter({ hasText: /error/i }).first().click({ force: true });
    }
  });
}

async function deleteDestinationByLabel(
  page: import('@playwright/test').Page,
  projectUuid: string,
  label: string,
): Promise<void> {
  await page.goto(`/projects/${projectUuid}/settings/alerts`);
  await dismissProductTour(page);
  const row = page.locator('#project-notification-destinations li').filter({ hasText: label }).first();
  if (!(await row.isVisible().catch(() => false))) {
    return;
  }
  const del = row.locator('form[action*="/delete"] button[type="submit"]').first();
  page.once('dialog', (d) => d.accept().catch(() => undefined));
  await del.click({ force: true });
  await waitForPageLoader(page);
}

test.describe('Notification channel matrix', () => {
  test('creates Discord destination then deletes (UC-NOTIF-10)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const label = `e2e-discord-${Date.now().toString(36)}`;
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible();
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('discord');
    await form
      .locator('input[name="notification_destination[endpointUrl]"]')
      .fill('https://discord.com/api/webhooks/000000000000000000/e2e-discord-token');
    await fillCategories(page, form);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/alerts`));
    await expect(page.getByRole('main')).toContainText(label, { timeout: 15_000 });
    await deleteDestinationByLabel(page, uuid, label);
  });

  test('creates Telegram destination then deletes (UC-NOTIF-11)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const label = `e2e-telegram-${Date.now().toString(36)}`;
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible();
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('telegram');
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill('123456:AAE2eTelegramToken@-1001234567890');
    await fillCategories(page, form);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/alerts`));
    await expect(page.getByRole('main')).toContainText(label, { timeout: 15_000 });
    await deleteDestinationByLabel(page, uuid, label);
  });

  test('creates email destination then deletes (UC-NOTIF-12)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const label = `e2e-email-${Date.now().toString(36)}`;
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible();
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('email');
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill('e2e-alerts@example.invalid');
    await fillCategories(page, form);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/alerts`));
    await expect(page.getByRole('main')).toContainText(label, { timeout: 15_000 });
    await deleteDestinationByLabel(page, uuid, label);
  });

  test('edits destination label and clears signing secret (UC-NOTIF-13/14)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const suffix = Date.now().toString(36);
    const label = `e2e-edit-${suffix}`;
    const updated = `${label}-renamed`;

    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('slack');
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill('https://hooks.slack.com/services/T/B/e2e-edit');
    await form.locator('input[name="notification_destination[signingSecret]"]').fill('e2e-signing-secret');
    await fillCategories(page, form);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);

    const row = page.locator('#project-notification-destinations li').filter({ hasText: label }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    await row.locator(`a[href*="/projects/${uuid}/notifications/"][href*="/edit"]`).click();
    await waitForPageLoader(page);

    const edit = page.getByRole('main').locator('form.notification-destination-form');
    await edit.locator('input[name="notification_destination[label]"]').fill(updated);
    const clear = edit.locator('input[name="notification_destination[clearSigningSecret]"]');
    if ((await clear.count()) > 0) {
      await clear.check();
    }
    await edit.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(updated, { timeout: 15_000 });
    await deleteDestinationByLabel(page, uuid, updated);
  });

  test('threshold rule edit form saves (UC-NOTIF-15)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const label = `e2e-thr-edit-${Date.now().toString(36)}`;
    await page.goto(`/projects/${uuid}/threshold-rules/new`);
    await dismissProductTour(page);
    const form = page.locator('form').filter({ has: page.locator('input[name="project_threshold_rule[label]"]') });
    await form.locator('input[name="project_threshold_rule[label]"]').fill(label);
    await form.locator('input[name="project_threshold_rule[errorCount]"]').fill('3');
    await form.locator('input[name="project_threshold_rule[windowMinutes]"]').fill('10');
    await form.locator('input[name="project_threshold_rule[cooldownMinutes]"]').fill('20');
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);

    const row = page.locator('li, tr, .panel').filter({ hasText: label }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    const edit = row.locator('a[href*="/threshold-rules/"][href*="/edit"]').first();
    await edit.click();
    await waitForPageLoader(page);
    const editForm = page.locator('form').filter({ has: page.locator('input[name="project_threshold_rule[errorCount]"]') });
    await editForm.locator('input[name="project_threshold_rule[errorCount]"]').fill('7');
    await editForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);

    const rowAfter = page.locator('li, tr, .panel').filter({ hasText: label }).first();
    if (await rowAfter.isVisible().catch(() => false)) {
      const del = rowAfter.locator('form[action*="/delete"] button[type="submit"]').first();
      if (await del.isVisible().catch(() => false)) {
        page.once('dialog', (d) => d.accept().catch(() => undefined));
        await del.click({ force: true });
        await waitForPageLoader(page);
      }
    }
  });

  test('rejects private/SSRF endpoint URL (UC-NOTIF-16)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form.notification-destination-form');
    await form.locator('input[name="notification_destination[label]"]').fill(`e2e-ssrf-${Date.now().toString(36)}`);
    await form.locator('select[name="notification_destination[type]"]').selectOption('http');
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill('http://127.0.0.1:9/internal');
    await fillCategories(page, form);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    // Stay on form with validation error — do not land on alerts list for this label.
    await expect(page).toHaveURL(/\/notifications\/new|\/notifications\//);
    await expect(page.locator('body')).toContainText(/private|ssrf|not allowed|no permitido|invalid|inválid/i);
  });
});
