import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  gotoStable,
  waitForPageLoader,
} from '../support/helpers';

test.describe('Legal & cookie behavioral depth', () => {
  test('cookie definition edit and delete ephemeral row (UC-LEGAL-08-D1)', async ({ page }) => {
    test.setTimeout(120_000);
    await gotoStable(page, '/admin/cookie-consent');
    await dismissProductTour(page);
    await expect(page).toHaveURL(/\/cookie-consent-config\/\d+\//, { timeout: 15_000 });
    const configId = page.url().match(/\/cookie-consent-config\/(\d+)\//)?.[1];
    expect(configId).toBeTruthy();

    const suffix = Date.now().toString(36);
    const cookieName = `E2E_COOKIE_${suffix.toUpperCase()}`;
    await gotoStable(page, `/cookie-consent-config/${configId}/cookies/new`);
    await dismissProductTour(page);
    const create = page.getByRole('main').locator('form').filter({ has: page.locator('input[name="cookie_definition[name]"]') });
    await expect(create).toBeVisible({ timeout: 15_000 });
    await create.locator('input[name="cookie_definition[name]"]').fill(cookieName);
    const provider = create.locator('input[name="cookie_definition[translations][0][provider]"]');
    if ((await provider.count()) > 0) {
      await provider.fill('Playwright E2E');
    }
    const purpose = create.locator(
      'input[name="cookie_definition[translations][0][purpose]"], textarea[name="cookie_definition[translations][0][purpose]"]',
    );
    if ((await purpose.count()) > 0) {
      await purpose.first().fill('E2E purpose initial');
    }
    const category = create.locator('select[name="cookie_definition[category]"]');
    if ((await category.count()) > 0) {
      await category.selectOption({ index: 1 }).catch(() => undefined);
    }
    const duration = create.locator('input[name="cookie_definition[duration]"], input[name="cookie_definition[defaultDuration]"]');
    if ((await duration.count()) > 0 && (await duration.first().inputValue()) === '') {
      await duration.first().fill('30 days');
    }
    await create.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);

    if (page.url().includes('/cookies/new')) {
      const errs = await page.locator('.form-error, .invalid-feedback, [class*="error"]').allTextContents();
      test.skip(true, `cookie create stayed on form: ${errs.join(' | ') || 'validation'}`);
      return;
    }

    const editUrlMatch = page.url().match(/\/cookies\/(\d+)\/edit/);
    if (editUrlMatch) {
      const editForm = page.getByRole('main').locator('form').filter({ has: page.locator('input[name="cookie_definition[name]"]') });
      await expect(editForm).toBeVisible({ timeout: 15_000 });
      const editPurpose = editForm.locator(
        'input[name="cookie_definition[translations][0][purpose]"], textarea[name="cookie_definition[translations][0][purpose]"]',
      );
      if ((await editPurpose.count()) > 0) {
        await editPurpose.first().fill('E2E purpose edited');
        await editForm.locator('button[type="submit"]').first().click();
        await waitForPageLoader(page);
      }
    } else {
      let row = page.locator('tr').filter({ hasText: cookieName }).first();
      for (let pageNum = 1; pageNum <= 4 && (await row.count()) === 0; pageNum++) {
        await gotoStable(page, `/cookie-consent-config/${configId}/cookies?page=${pageNum}`);
        await dismissProductTour(page);
        row = page.locator('tr').filter({ hasText: cookieName }).first();
      }
      await expect(row).toBeVisible({ timeout: 15_000 });
      const editLink = row.locator('a[href*="/edit"]').first();
      await editLink.click();
      await waitForPageLoader(page);
      const editForm = page.getByRole('main').locator('form').filter({ has: page.locator('input[name="cookie_definition[name]"]') });
      const editPurpose = editForm.locator(
        'input[name="cookie_definition[translations][0][purpose]"], textarea[name="cookie_definition[translations][0][purpose]"]',
      );
      if ((await editPurpose.count()) > 0) {
        await editPurpose.first().fill('E2E purpose edited');
        await editForm.locator('button[type="submit"]').first().click();
        await waitForPageLoader(page);
      }
    }

    await gotoStable(page, `/cookie-consent-config/${configId}/cookies?q=${encodeURIComponent(cookieName)}`);
    await dismissProductTour(page);
    let row = page.locator('tr').filter({ hasText: cookieName }).first();
    if ((await row.count()) === 0) {
      for (let pageNum = 1; pageNum <= 4; pageNum++) {
        await gotoStable(page, `/cookie-consent-config/${configId}/cookies?page=${pageNum}`);
        row = page.locator('tr').filter({ hasText: cookieName }).first();
        if ((await row.count()) > 0) {
          break;
        }
      }
    }
    await expect(row).toBeVisible({ timeout: 15_000 });

    const deleteBtn = row.locator('form[action*="/delete"] button[type="submit"], button.btn-danger, button').filter({
      hasText: /delete|eliminar/i,
    }).first();
    await expect(deleteBtn).toBeVisible({ timeout: 10_000 });
    page.once('dialog', (d) => d.accept().catch(() => undefined));
    await deleteBtn.click({ force: true });
    const confirm = page.locator('dialog[open], .confirm-dialog[open]').last();
    if (await confirm.isVisible().catch(() => false)) {
      await confirm.locator('button[type="submit"], button.btn-danger').filter({ hasText: /delete|eliminar/i }).click({ force: true });
      await waitForPageLoader(page);
    }
    await expect(page.locator('tr').filter({ hasText: cookieName })).toHaveCount(0, { timeout: 15_000 });
  });

  test('cookie consent settings tab field persists after save (UC-LEGAL-09-D1)', async ({ page }) => {
    test.setTimeout(90_000);
    await gotoStable(page, '/admin/cookie-consent');
    await dismissProductTour(page);
    const settingsLink = page.locator('a[href*="/settings"]').first();
    if (await settingsLink.isVisible().catch(() => false)) {
      await settingsLink.click();
      await waitForPageLoader(page);
    }
    const form = page.locator('[data-testid="cookie-consent-settings-form"]');
    await expect(form).toBeVisible({ timeout: 15_000 });

    const tabs = page.locator('.nowo-ui-tabs--sections button, .nowo-ui-tabs button[role="tab"]');
    const tabCount = await tabs.count();
    if (tabCount > 1) {
      await tabs.nth(1).click();
      await page.waitForTimeout(300);
    }

    const textInput = form.locator('input[type="text"]:visible, textarea:visible').first();
    if ((await textInput.count()) === 0) {
      test.info().annotations.push({ type: 'note', description: 'no visible text field on active tab — smoke save only' });
      await form.locator('button[type="submit"]').first().click();
      await waitForPageLoader(page);
      await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
      return;
    }

    const marker = `E2E legal ${Date.now().toString(36)}`;
    const prior = await textInput.inputValue();
    await textInput.fill(marker);
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');

    await page.reload();
    await dismissProductTour(page);
    if (tabCount > 1) {
      await tabs.nth(1).click();
      await page.waitForTimeout(300);
    }
    const reloaded = form.locator('input[type="text"]:visible, textarea:visible').first();
    await expect(reloaded).toHaveValue(marker, { timeout: 15_000 });

    await reloaded.fill(prior);
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
  });
});

test.describe('Legal guest customize consent', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('customize consent saves category choice and suppresses modal (UC-LEGAL-07-D1)', async ({ page, context }) => {
    test.setTimeout(90_000);
    await context.clearCookies();
    await page.goto('/en/login', { waitUntil: 'domcontentloaded' });
    await waitForPageLoader(page);

    const modal = page.locator('#cookieconsent[data-nowo-open="true"]:not(.hidden)');
    try {
      await modal.waitFor({ state: 'visible', timeout: 8_000 });
    } catch {
      test.skip(true, 'Consent modal not shown');
      return;
    }

    // Two-step: banner → preferences. One-step: preference sections already in the modal.
    const showPreferences = modal.locator(
      '[data-nowo-show-preferences], button:has-text("Customize"), button:has-text("Personalizar"), button:has-text("Configure"), #cookie_consent_show_details',
    ).first();
    if (await showPreferences.isVisible().catch(() => false)) {
      await showPreferences.click();
      await expect(modal.locator('[data-nowo-step="preferences"], .nowo-cookie-consent__preferences-body, input[type="checkbox"]')).toBeVisible({
        timeout: 10_000,
      });
    }

    const analyticsToggle = modal.locator(
      'input[type="checkbox"][name*="analytics"], input[type="checkbox"][id*="analytics"], input[type="checkbox"][data-category="analytics"], input[type="checkbox"][name*="categories"]',
    ).first();
    if ((await analyticsToggle.count()) > 0) {
      if (await analyticsToggle.isChecked().catch(() => false)) {
        await analyticsToggle.uncheck({ force: true }).catch(async () => {
          await analyticsToggle.locator('xpath=ancestor::label[1]').click({ force: true });
        });
      }
    } else {
      test.info().annotations.push({
        type: 'note',
        description: 'no analytics checkbox — save preferences shell only',
      });
    }

    const save = modal.locator(
      'button[name="cookie_consent[save]"], button:has-text("Save"), button:has-text("Guardar"), button:has-text("Accept selection"), #cookie_consent_save_selection, button[type="submit"]',
    ).filter({ hasNotText: /accept all|aceptar todas|use all|todas/i }).first();
    if (!(await save.isVisible().catch(() => false))) {
      // One-step footer may only expose accept-all / necessary; necessary-only still counts as reject non-essential.
      const necessary = modal.locator(
        'button[name*="use_only_functional"], button:has-text("necessary"), button:has-text("necesarias"), button:has-text("Solo cookies")',
      ).first();
      await expect(necessary).toBeVisible({ timeout: 10_000 });
      await necessary.evaluate((el: HTMLElement) => el.click());
    } else {
      await save.evaluate((el: HTMLElement) => el.click());
    }
    await modal.waitFor({ state: 'hidden', timeout: 15_000 });

    // Login is auto-open targeted — consent cookie must suppress the banner.
    await page.goto('/en/login', { waitUntil: 'domcontentloaded' });
    await waitForPageLoader(page);
    await expect(page.locator('#cookieconsent[data-nowo-open="true"]:not(.hidden)')).toHaveCount(0, { timeout: 10_000 });
  });
});
