import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from './helpers';

test.describe('Account area deep checks', () => {
  test('profile overview exposes identity meta', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/profile');
    await expect(page.locator('[data-testid="profile-overview"]')).toBeVisible();
    await expect(page.locator('[data-testid="profile-account-meta"]')).toBeVisible();
    await expect(page.locator('[data-testid="profile-account-meta"] dd').first()).not.toHaveText('');
  });

  test('account area nav switches between sections', async ({ page }) => {
    await page.goto('/account/profile');
    await dismissProductTour(page);

    const nav = page.locator('[data-testid="account-area-nav"]');
    if ((await nav.count()) === 0) {
      // Older layouts may omit the marker — fall back to direct links.
      await page.locator('a[href*="/account/security"]').first().click();
    } else {
      await nav.locator('a[href*="/account/security"]').first().click();
    }
    await expect(page).toHaveURL(/\/account\/security/);
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('security, history, and activity panels render', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/security');
    await expect(page.locator('[data-testid="linked-social-accounts"]')).toBeVisible();
    await expect(page.getByRole('main')).toContainText(/seguridad|security|password|contraseña/i);

    await expectAuthenticatedPage(page, '/account/security/history');
    await expect(page.locator('[data-testid="password-change-history"]')).toBeVisible();

    await expectAuthenticatedPage(page, '/account/security/activity');
    await expect(page.locator('[data-testid="security-activity"]')).toBeVisible();
  });

  test('projects and groups lists render', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/projects');
    await expect(page.locator('[data-testid="profile-projects"]')).toBeVisible();
    await expectAuthenticatedPage(page, '/account/groups');
    await expect(page.locator('[data-testid="profile-groups"]')).toBeVisible();
  });

  test('privacy export and anonymize panels are present', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/privacy');
    await expect(page.locator('[data-testid="account-privacy-export"]')).toBeVisible();
    await expect(page.locator('[data-testid="account-privacy-export-btn"]')).toBeVisible();
    await expect(page.locator('[data-testid="account-privacy-anonymize"]')).toBeVisible();
  });

  test('display preference forms load', async ({ page }) => {
    for (const path of [
      '/account/display',
      '/account/display/panels',
      '/account/display/tours',
    ]) {
      await expectAuthenticatedPage(page, path);
      await expect(page.getByRole('main').locator('form').first()).toBeVisible();
    }

    // Notifications: form only when VAPID is configured; otherwise the unavailable panel (no form).
    await expectAuthenticatedPage(page, '/account/display/notifications');
    const main = page.getByRole('main');
    const pushForm = main.locator('[data-testid="display-push-form"], form').first();
    const pushUnavailable = main.locator('[data-testid="display-push-unavailable"]');
    await expect(pushForm.or(pushUnavailable)).toBeVisible();
  });

  test('authenticated locale switch posts without 500', async ({ page }) => {
    await page.goto('/dashboard');
    await dismissProductTour(page);

    const es = page.locator('form[action="/account/locale/es"] button[type="submit"], form[action*="/account/locale/es"] button').first();
    if (!(await es.isVisible().catch(() => false))) {
      return;
    }
    await es.click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('html')).toHaveAttribute('lang', /es/i);

    const en = page.locator('form[action="/account/locale/en"] button[type="submit"], form[action*="/account/locale/en"] button').first();
    if (await en.isVisible().catch(() => false)) {
      await en.click();
      await waitForPageLoader(page);
    }
  });
});
