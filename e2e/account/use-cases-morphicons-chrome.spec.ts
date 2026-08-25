import { test, expect } from '@playwright/test';
import {
  dismissCookieConsent,
  dismissProductTour,
  gotoStable,
  waitForPageLoader,
} from '../support/helpers';

/**
 * UC-ACC-26 — Morphicons chrome hydration on authenticated shell + login password toggle.
 * Asserts `<morph-icon>` hosts become `is-morph-ready` after app.ts paints Lucide data.
 */

test.describe('Morphicons chrome (UC-ACC-26)', () => {
  test('theme, content-width, and sidebar morph icons hydrate on dashboard', async ({ page }) => {
    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);

    const theme = page.locator('[data-theme-toggle]').first();
    await expect(theme).toBeVisible({ timeout: 15_000 });
    await expect(theme).toHaveClass(/is-morph-ready/, { timeout: 15_000 });
    await expect(theme.locator('morph-icon[data-theme-morph] svg')).toBeVisible();

    const width = page.locator('[data-content-width-toggle]').first();
    if (await width.isVisible().catch(() => false)) {
      await expect(width).toHaveClass(/is-morph-ready/, { timeout: 15_000 });
      await expect(width.locator('morph-icon[data-content-width-morph] svg')).toBeVisible();
    }

    const sidebar = page.locator('[data-sidebar-toggle]').first();
    if (await sidebar.isVisible().catch(() => false)) {
      await expect(sidebar).toHaveClass(/is-morph-ready/, { timeout: 15_000 });
      await expect(sidebar.locator('morph-icon[data-sidebar-morph] svg')).toBeVisible();
    }

    // Theme click should keep a painted morph host (not FOUC static-only).
    await theme.click();
    await waitForPageLoader(page);
    await expect(theme).toHaveClass(/is-morph-ready/);
    await expect(theme.locator('morph-icon[data-theme-morph] svg')).toBeVisible();
  });
});

test.describe('Morphicons chrome — guest password (UC-ACC-26)', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('password morph icon hydrates on login', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await dismissCookieConsent(page);

    // PasswordToggleBundle uses a span[data-controller="password-toggle"], not a <button>.
    const toggle = page.locator('.form-password-toggle [data-controller="password-toggle"]').first();
    await expect(toggle).toBeVisible({ timeout: 15_000 });
    await expect(toggle).toHaveClass(/is-morph-ready/, { timeout: 15_000 });
    await expect(toggle.locator('morph-icon[data-password-morph] svg')).toBeVisible();

    await toggle.click();
    await expect(toggle).toHaveClass(/is-morph-ready/);
    await expect(toggle.locator('morph-icon[data-password-morph] svg')).toBeVisible();
  });
});
