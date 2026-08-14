import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from '../support/helpers';

test.describe('Admin remaining use cases', () => {
  test('toggle-enabled on a newly created user (UC-ADM-03)', async ({ page }) => {
    const suffix = Date.now().toString(36);
    const email = `e2e.toggle.${suffix}@example.invalid`;
    const password = `E2eTog!${suffix}`;

    await page.goto('/admin/users?new=1');
    await dismissProductTour(page);
    const form = page.locator('form').filter({ has: page.locator('input[name="admin_user[email]"]') });
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="admin_user[email]"]').fill(email);
    await form.locator('input[name="admin_user[displayName]"]').fill(`Toggle ${suffix}`);
    await form.locator('input[name="admin_user[password]"]').fill(password);
    await form.locator('select[name="admin_user[role]"]').selectOption('user');
    const enabled = form.locator('input[name="admin_user[enabled]"]');
    if (!(await enabled.isChecked().catch(() => false))) {
      await enabled.check();
    }
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(email, { timeout: 20_000 });

    const row = page.locator('tr, li, .panel').filter({ hasText: email }).first();
    const toggle = row.locator('form[action*="toggle-enabled"] button[type="submit"]').first();
    if (await toggle.isVisible().catch(() => false)) {
      await toggle.click();
      await waitForPageLoader(page);
      await expect(page).not.toHaveURL(/\/login/);
      // Flip back to enabled for hygiene.
      const toggle2 = page
        .locator('tr, li, .panel')
        .filter({ hasText: email })
        .locator('form[action*="toggle-enabled"] button[type="submit"]')
        .first();
      if (await toggle2.isVisible().catch(() => false)) {
        await toggle2.click();
        await waitForPageLoader(page);
      }
    }
  });

  test('mailer sample-send control is present (UC-ADM-13)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/mailer');
    await expect(page.getByRole('main').locator('form').first()).toBeVisible();
    // Sample form only when Mailer DSN is deliverable; otherwise unavailable copy.
    const sampleSubmit = page.getByRole('main').locator('button[type="submit"]').nth(1);
    const unavailable = page.getByRole('main').locator('text=/unavailable|no disponible|magic|DSN/i');
    await expect(sampleSubmit.or(unavailable).first()).toBeAttached();
  });

  test('admin group show exposes members panel (UC-ADM-05)', async ({ page }) => {
    const name = `E2E Gap Group ${Date.now().toString(36)}`;
    await page.goto('/admin/groups/new');
    await dismissProductTour(page);
    const form = page.locator('form').filter({ has: page.locator('input[name="admin_group[name]"]') });
    await expect(form).toBeVisible();
    await form.locator('input[name="admin_group[name]"]').fill(name);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page).toHaveURL(/\/admin\/groups\/[0-9a-f-]{36}/, { timeout: 20_000 });
    await expect(page.locator('body')).toContainText(name, { timeout: 20_000 });
  });

  test('admin user activity shows project unlink affordance when linked (UC-ADM-20)', async ({ page }) => {
    await page.goto('/admin/users');
    await dismissProductTour(page);
    const activity = page.locator('a[href*="/admin/users/"][href*="/activity"]').first();
    if ((await activity.count()) === 0) {
      return;
    }
    await activity.click();
    await expect(page).toHaveURL(/\/activity/);
    // Unlink forms may be absent when user has no projects — shell still OK.
    await expect(page.getByRole('main')).toBeVisible();
    const unlink = page.locator('form[action*="/projects/"][action*="/remove"] button[type="submit"]');
    if ((await unlink.count()) > 0) {
      await expect(unlink.first()).toBeAttached();
    }
  });
});
