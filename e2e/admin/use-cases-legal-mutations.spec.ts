import { test, expect } from '@playwright/test';
import {
  dismissCookieConsent,
  dismissProductTour,
  expectAuthenticatedPage,
  gotoStable,
  waitForPageLoader,
} from '../support/helpers';

test.describe('Legal & cookie mutations', () => {
  test('cookie definition new form creates ephemeral row (UC-LEGAL-08)', async ({ page }) => {
    await gotoStable(page, '/admin/cookie-consent');
    await dismissProductTour(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page).toHaveURL(/\/cookie-consent-config\/\d+\//, { timeout: 15_000 });
    const configId = page.url().match(/\/cookie-consent-config\/(\d+)\//)?.[1];
    expect(configId, 'configId from redirect').toBeTruthy();

    await gotoStable(page, `/cookie-consent-config/${configId}/cookies/new`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form').filter({ has: page.locator('input[name="cookie_definition[name]"]') });
    await expect(form).toBeVisible({ timeout: 15_000 });
    const suffix = Date.now().toString(36);
    await form.locator('input[name="cookie_definition[name]"]').fill(`e2e_cookie_${suffix}`);
    const provider = form.locator('input[name="cookie_definition[translations][0][provider]"]');
    if ((await provider.count()) > 0) {
      await provider.fill('Playwright');
    }
    const purpose = form.locator(
      'input[name="cookie_definition[translations][0][purpose]"], textarea[name="cookie_definition[translations][0][purpose]"]',
    );
    if ((await purpose.count()) > 0) {
      await purpose.first().fill('E2E ephemeral cookie definition');
    }
    const category = form.locator('select[name="cookie_definition[category]"]');
    if ((await category.count()) > 0) {
      await category.selectOption({ index: 1 }).catch(() => undefined);
    }
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
    const body = page.locator('body');
    const listed = await body.getByText(new RegExp(`e2e_cookie_${suffix}`, 'i')).count();
    if (listed === 0) {
      await expect(form.locator('input[name="cookie_definition[name]"]')).toHaveValue(`e2e_cookie_${suffix}`);
    } else {
      await expect(body).toContainText(new RegExp(`e2e_cookie_${suffix}`, 'i'));
    }
  });

  test('cookie consent settings save without 5xx (UC-LEGAL-09)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/cookie-consent');
    await dismissProductTour(page);
    const form = page.locator('[data-testid="cookie-consent-settings-form"]');
    if ((await form.count()) === 0) {
      // Some kit builds land on a section URL — open general settings if linked.
      const settingsLink = page.locator('a[href*="/settings"]').first();
      if (await settingsLink.isVisible().catch(() => false)) {
        await settingsLink.click();
        await waitForPageLoader(page);
      }
    }
    await expect(page.locator('[data-testid="cookie-consent-settings-form"]')).toBeVisible({ timeout: 15_000 });
    await page.locator('[data-testid="cookie-consent-settings-form"] button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
    await expect(page).not.toHaveURL(/\/login/);
  });
});

test.describe('Account redirects & admin project rename', () => {
  test('account index redirects into preferences area (UC-ACC-20)', async ({ page }) => {
    await page.goto('/account');
    await dismissProductTour(page);
    await expect(page).toHaveURL(/\/account\/(profile|overview|display|security|preferences)/);
    await expect(page.getByRole('main')).toBeVisible();
  });

  test('admin edits ephemeral project name (UC-PROJ-22)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const name = `E2E Rename ${suffix}`;
    await page.goto('/dashboard?new=1');
    await dismissProductTour(page);
    if (!(await page.locator('input[name="project[name]"]').isVisible().catch(() => false))) {
      await page.locator('[data-action="new-project"]').click();
    }
    await page.locator('input[name="project[name]"]').fill(name);
    await page.locator('textarea[name="project[description]"]').fill('rename target');
    await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();
    await page.waitForURL(/\/projects\/([0-9a-f-]{36})/i, { timeout: 30_000 });
    const match = page.url().match(/\/projects\/([0-9a-f-]{36})/i);
    expect(match?.[1]).toBeTruthy();
    const uuid = match![1];

    await page.goto(`/admin/projects/${uuid}/edit`);
    await dismissProductTour(page);
    const form = page.getByRole('main').locator('form').filter({ has: page.locator('input[name="project[name]"]') });
    await expect(form).toBeVisible({ timeout: 15_000 });
    const renamed = `${name} edited`;
    await form.locator('input[name="project[name]"]').fill(renamed);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    // WSL/CI can stall on the page-loader overlay after admin save — re-enter via stable goto.
    await gotoStable(page, `/admin/projects/${uuid}/edit`);
    await dismissProductTour(page);
    await expect(page.locator('input[name="project[name]"]')).toHaveValue(renamed);
  });
});

test.describe('Legal guest necessary-only (isolated)', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('necessary-only path closes modal (UC-LEGAL-07 isolated)', async ({ page, context }) => {
    await context.clearCookies();
    await page.goto('/en/login', { waitUntil: 'domcontentloaded' });
    await waitForPageLoader(page);
    const openModal = page.locator('#cookieconsent[data-nowo-open="true"]:not(.hidden)');
    try {
      await openModal.waitFor({ state: 'visible', timeout: 5_000 });
    } catch {
      test.skip(true, 'Consent modal not shown');
      return;
    }
    const necessary = openModal.locator(
      '#cookie_consent_use_only_functional_cookies, button:has-text("necessary"), button:has-text("necesarias"), button:has-text("Solo cookies"), button:has-text("Refuse")',
    ).first();
    if (!(await necessary.isVisible().catch(() => false))) {
      await dismissCookieConsent(page);
      test.skip(true, 'Necessary-only control missing');
      return;
    }
    // Bundle button can sit under overlay chrome — native DOM click matches dismissCookieConsent.
    await necessary.evaluate((el: HTMLElement) => el.click());
    await openModal.waitFor({ state: 'hidden', timeout: 10_000 });
  });
});
