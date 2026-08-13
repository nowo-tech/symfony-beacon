import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  expectAuthenticatedPage,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

test.describe('Member alert preferences (091)', () => {
  test('account notifications Live shell and event rows render', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/display/notifications');
    await expect(page.locator('[data-testid="member-alerts-section"]')).toBeVisible();
    await expect(page.locator('[data-testid="member-alert-preferences-live"]')).toBeVisible();
    await expect(page.locator('[data-testid="member-alert-preferences-form"]')).toBeVisible();
    await expect(page.locator('[data-testid="member-alerts-events"]')).toBeVisible();
    await expect(page.locator('[data-testid="member-alert-event-row"]').first()).toBeVisible();
    await expect(page.locator('[data-testid="member-alert-event-scope"]').first()).toBeVisible();
    await expect(page.locator('[data-testid="member-alerts-projects-hint"]')).toBeVisible();
    await expect(page.locator('[data-testid="member-alerts-save"]')).toBeVisible();
  });

  test('master switch hides events and shows off hint (Live)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/display/notifications');
    const form = page.locator('[data-testid="member-alert-preferences-form"]');
    const master = form.locator('input[name*="memberAlertsEnabled"]');
    await expect(master).toBeVisible();

    // Ensure known starting state: master ON.
    if (!(await master.isChecked())) {
      await master.click({ force: true });
      await expect(page.locator('[data-testid="member-alerts-events"]')).toBeVisible({ timeout: 15_000 });
    }

    await master.click({ force: true });
    await expect(page.locator('[data-testid="member-alerts-master-off-hint"]')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('[data-testid="member-alerts-events"]')).toHaveCount(0);

    // Restore ON without persisting so later tests keep defaults.
    await master.click({ force: true });
    await expect(page.locator('[data-testid="member-alerts-events"]')).toBeVisible({ timeout: 15_000 });
  });

  test('disabling an event hides its scope switch (Live)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/display/notifications');
    const row = page.locator('[data-testid="member-alert-event-row"]').first();
    await expect(row).toBeVisible();
    await expect(row.locator('[data-testid="member-alert-event-scope"]')).toBeVisible();

    const enabled = row.locator('input[name*="[enabled]"]');
    await expect(enabled).toBeChecked();
    await enabled.click({ force: true });
    await expect(row.locator('[data-testid="member-alert-event-scope"]')).toHaveCount(0, { timeout: 15_000 });

    // Restore for subsequent navigations (unsaved Live state only).
    await enabled.click({ force: true });
    await expect(row.locator('[data-testid="member-alert-event-scope"]')).toBeVisible({ timeout: 15_000 });
  });

  test('account page opens project override dialog', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/display/notifications');
    const openBtn = page.locator('[data-testid="member-alerts-project-open"]').first();
    await expect(openBtn).toBeVisible({ timeout: 15_000 });
    await openBtn.click();

    const dialog = page.locator('dialog[open], .confirm-dialog[open]').first();
    await expect(dialog).toBeVisible({ timeout: 10_000 });
    await expect(dialog.locator('[data-testid="member-alerts-project-form"]')).toBeVisible();
    await expect(
      dialog
        .locator('[data-testid="member-alerts-project-overrides"], [data-testid="member-alerts-project-off-hint"]')
        .first(),
    ).toBeVisible();
    await expect(dialog.locator('[data-testid="member-alerts-project-save"]')).toBeVisible();

    const close = dialog.locator('[data-confirm-dialog-close], button[data-confirm-dialog-close]').first();
    if (await close.isVisible().catch(() => false)) {
      await close.click();
    } else {
      await page.keyboard.press('Escape');
    }
    await dialog.waitFor({ state: 'hidden', timeout: 10_000 }).catch(() => undefined);
  });

  test('project settings member-alerts panel cascades and saves', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings#member-alerts`);
    await dismissProductTour(page);

    const panel = page.locator('[data-testid="project-member-alerts"]');
    await expect(panel).toBeVisible();
    await expect(panel.locator('[data-testid="member-project-alert-preferences-live"]')).toBeVisible();
    await expect(panel.locator('[data-testid="member-alerts-project-form"]')).toBeVisible();
    await expect(panel.locator('[data-testid="member-alerts-project-overrides"]')).toBeVisible();
    await expect(panel.locator('[data-testid="project-member-alerts-save"]')).toBeVisible();

    // Project master: …[enabled] but not …[events][…][enabled].
    const projectSwitch = panel.locator(
      '[data-testid="member-alerts-project-form"] input[name$="[enabled]"]:not([name*="[events]"])',
    );
    await expect(projectSwitch).toBeChecked();
    await projectSwitch.click({ force: true });
    await expect(panel.locator('[data-testid="member-alerts-project-off-hint"]')).toBeVisible({ timeout: 15_000 });
    await expect(panel.locator('[data-testid="member-alerts-project-overrides"]')).toHaveCount(0);

    // Re-enable and persist so demo prefs stay usable.
    await projectSwitch.click({ force: true });
    await expect(panel.locator('[data-testid="member-alerts-project-overrides"]')).toBeVisible({ timeout: 15_000 });
    await panel.locator('[data-testid="project-member-alerts-save"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings`));
    await expect(page.locator('[data-testid="project-member-alerts"]')).toBeVisible();
    await expect(page.locator('[data-testid="member-alerts-project-overrides"]')).toBeVisible();
  });

  test('account defaults link from project settings reaches notifications', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings#member-alerts`);
    await dismissProductTour(page);
    const link = page.locator('[data-testid="project-member-alerts"] a[href*="/account/display/notifications"]');
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/\/account\/display\/notifications/);
    await expect(page.locator('[data-testid="member-alert-preferences-live"]')).toBeVisible();
  });
});
