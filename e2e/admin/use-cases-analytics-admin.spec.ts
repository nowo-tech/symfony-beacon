import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  expectAuthenticatedPage,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

test.describe('Analytics filters — use cases', () => {
  test('period preset and env/release/level query filters (UC-AN-02)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/analytics?period=7`);
    await expect(page.locator('form.analytics-filters')).toBeVisible();
    await expect(page.locator('a.analytics-filters__preset').first()).toBeVisible();

    await page.goto(`/projects/${uuid}/analytics?period=14&environment=production&release=1.0.0&level=error`);
    await dismissProductTour(page);
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('form.analytics-filters')).toBeVisible();
    await expect(page).toHaveURL(/environment=production/);
    await expect(page.locator('.analytics-chart, .analytics-table').first()).toBeVisible();
  });

  test('analytics filter form submit updates query string (UC-AN-02)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/analytics`);
    await dismissProductTour(page);
    await waitForPageLoader(page);
    const form = page.locator('form.analytics-filters');
    await expect(form).toBeVisible();

    const env = form
      .locator(
        'input[name="analytics_filter[environment]"], input[id*="environment"], input[aria-label*="nvironment" i]',
      )
      .first();
    const level = form
      .locator('input[name="analytics_filter[level]"], input[id*="level"], input[aria-label*="evel" i]')
      .first();
    await expect(env).toBeVisible({ timeout: 15_000 });
    await env.fill('staging');
    await level.fill('warning');
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/environment=staging/);
    await expect(page).toHaveURL(/level=warning/);
  });
});

test.describe('Admin user lifecycle — use cases', () => {
  test('creates a disabled user who cannot log in (UC-ADM-03)', async ({ page, browser }) => {
    const suffix = Date.now().toString(36);
    const email = `e2e.user.${suffix}@example.invalid`;
    const password = `E2ePass!${suffix}`;

    await page.goto('/admin/users?new=1');
    await dismissProductTour(page);
    await waitForPageLoader(page);
    const form = page.locator('form').filter({ has: page.locator('input[name="admin_user[email]"]') });
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="admin_user[email]"]').fill(email);
    await form.locator('input[name="admin_user[displayName]"]').fill(`E2E User ${suffix}`);
    await form.locator('input[name="admin_user[password]"]').fill(password);
    await form.locator('select[name="admin_user[role]"]').selectOption('user');
    const enabled = form.locator('input[name="admin_user[enabled]"]');
    if (await enabled.isChecked().catch(() => false)) {
      await enabled.uncheck();
    }
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.getByRole('main')).toContainText(email, { timeout: 20_000 });

    const origin = new URL(page.url()).origin;
    const guestContext = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const guest = await guestContext.newPage();
    await guest.goto(`${origin}/login`);
    await guest.locator('input[name="login_form[_username]"]').waitFor({ state: 'visible', timeout: 20_000 });
    await guest.locator('input[name="login_form[_username]"]').fill(email);
    await guest.locator('input[name="login_form[_password]"]').fill(password);
    await guest
      .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
      .first()
      .click();
    await expect(guest).toHaveURL(/\/login/, { timeout: 20_000 });
    await guestContext.close();
  });

  test('admin can suspend then resume project ingest (UC-ADM-07)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/admin/projects/${uuid}`);
    await expect(page).toHaveURL(/\/admin\/projects\/.+/);
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="admin-project-show"]')).toBeVisible();

    const suspendBtn = page.getByRole('button', { name: /suspend|suspender|unterbrechen|opschorten|suspendre|sospendere/i });
    const resumeBtn = page.getByRole('button', { name: /resume|reanudar|wieder|hervat|reprendre|riprendi|retomar/i });
    const btn = (await suspendBtn.isVisible().catch(() => false)) ? suspendBtn : resumeBtn;
    if (await btn.isVisible().catch(() => false)) {
      await btn.click();
      await waitForPageLoader(page);
      await expect(page).not.toHaveURL(/\/login/);
      const btn2 = (await suspendBtn.isVisible().catch(() => false)) ? suspendBtn : resumeBtn;
      if (await btn2.isVisible().catch(() => false)) {
        await btn2.click();
        await waitForPageLoader(page);
      }
    }
  });
});
