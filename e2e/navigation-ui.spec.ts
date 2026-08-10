import { test, expect } from '@playwright/test';
import {
  dismissCookieConsent,
  dismissProductTour,
  expectAuthenticatedPage,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

test.describe('UI chrome & navigation', () => {
  test('project nav tabs switch between sections', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/issues`);
    await dismissProductTour(page);

    const nav = page.locator('[data-tour="project-nav"]');
    await expect(nav).toBeVisible();

    await nav.getByRole('link', { name: /performance|rendimiento/i }).click();
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/performance`));

    await nav.getByRole('link', { name: /analytics|analítica|analitica/i }).click();
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/analytics`));

    await nav.getByRole('link', { name: /releases|versiones/i }).click();
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/releases`));

    await nav.locator('[data-tour="project-settings"], a[href*="/settings"]').first().click();
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings`));

    await nav.getByRole('link', { name: /issues|incidencias|errores/i }).click();
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/issues`));
  });

  test('theme toggle flips data-theme', async ({ page }) => {
    await page.goto('/dashboard');
    await dismissProductTour(page);

    const html = page.locator('html');
    const before = await html.getAttribute('data-theme');
    await page.locator('[data-theme-toggle]').click();
    await page.waitForTimeout(200);
    const after = await html.getAttribute('data-theme');
    expect(after).toBeTruthy();
    // Toggle should change light↔dark (or set an explicit mode from system).
    if (before === 'light' || before === 'dark') {
      expect(after).not.toEqual(before);
    } else {
      expect(['light', 'dark']).toContain(after);
    }
  });

  test('content width toggle is clickable', async ({ page }) => {
    await page.goto('/dashboard');
    await dismissProductTour(page);
    const toggle = page.locator('[data-content-width-toggle]');
    await expect(toggle).toBeVisible();
    await toggle.click();
    await page.waitForTimeout(150);
    await expect(toggle).toBeVisible();
  });

  test('admin hub cards navigate to sections', async ({ page }) => {
    await page.goto('/admin');
    await dismissProductTour(page);
    const users = page.locator('a[href*="/admin/users"]').first();
    await expect(users).toBeVisible();
    await users.click();
    await expect(page).toHaveURL(/\/admin\/users/);
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('appearance form is present', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/appearance');
    const form = page.locator('[data-testid="appearance-form"]');
    if ((await form.count()) > 0) {
      await expect(form.first()).toBeVisible();
    } else {
      await expect(page.getByRole('main').locator('form').first()).toBeVisible();
    }
  });

  test('mailer and mercure settings show forms', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/mailer');
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
    await expectAuthenticatedPage(page, '/admin/mercure');
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
  });
});

test.describe('Guest access control', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('protected routes redirect to login', async ({ page }) => {
    for (const path of ['/dashboard', '/admin', '/account', '/projects/new', '/admin/mailer']) {
      await page.goto(path);
      await dismissCookieConsent(page);
      await expect(page, path).toHaveURL(/\/login/);
    }
  });

  test('guest locale switcher posts without 500', async ({ page }) => {
    await page.goto('/en/login');
    await dismissCookieConsent(page);
    const es = page.locator('form[action="/locale/es"] button[type="submit"], form[action*="/locale/es"] button').first();
    if (await es.isVisible().catch(() => false)) {
      await es.click();
      await waitForPageLoader(page);
      await expect(page).toHaveURL(/\/(es\/)?login/);
      await expect(page.locator('html')).toHaveAttribute('lang', /es/i);
    }
  });

  test('metrics endpoint responds without 5xx', async ({ request }) => {
    const res = await request.get('/metrics');
    expect(res.status()).toBeLessThan(500);
  });
});
