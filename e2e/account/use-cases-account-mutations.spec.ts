import { test, expect } from '@playwright/test';
import {
  DEMO_PASSWORD,
  dismissProductTour,
  waitForPageLoader,
} from '../support/helpers';

test.describe('Account mutations — profile & password', () => {
  test('saves profile display name (UC-ACC-17)', async ({ page }) => {
    await page.goto('/account/profile');
    await dismissProductTour(page);
    const form = page.locator('[data-testid="profile-basic-form"]');
    await expect(form).toBeVisible({ timeout: 15_000 });
    const nameInput = form.locator('input[name="user_profile[displayName]"]');
    const previous = await nameInput.inputValue();
    const next = `E2E Admin ${Date.now().toString(36).slice(-4)}`;
    await nameInput.fill(next);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(form.locator('input[name="user_profile[displayName]"]')).toHaveValue(next);

    // Restore so later suites keep a stable label.
    await nameInput.fill(previous || 'Admin');
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
  });

  test('saves PhoneInput country + national number as E.164 (UC-ACC-27)', async ({ page }) => {
    await page.goto('/account/profile');
    await dismissProductTour(page);
    const form = page.locator('[data-testid="profile-basic-form"]');
    await expect(form).toBeVisible({ timeout: 15_000 });

    const country = form.locator('select[name="user_profile[phone][country_iso]"]');
    const national = form.locator('input[name="user_profile[phone][national_number]"]');
    await expect(country).toBeVisible({ timeout: 15_000 });
    await expect(national).toBeVisible();

    const previousCountry = await country.inputValue();
    const previousNational = await national.inputValue();

    await country.selectOption('ES');
    await national.fill('600111222');
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/account\/profile/);
    await expect(form.locator('select[name="user_profile[phone][country_iso]"]')).toHaveValue('ES');
    await expect(form.locator('input[name="user_profile[phone][national_number]"]')).toHaveValue(
      /600\s?111\s?222|600111222/,
    );
    await expect(page.locator('[data-testid="phone-verification-status"]')).toBeVisible();

    // Clear phone so the seeded admin profile stays clean for later suites.
    await country.selectOption(previousCountry || 'ES');
    await national.fill(previousNational || '');
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
  });

  test('rejects email change without current password (UC-ACC-18)', async ({ page }) => {
    await page.goto('/account/profile');
    await dismissProductTour(page);
    const form = page.locator('[data-testid="profile-sensitive-form"]');
    await expect(form).toBeVisible({ timeout: 15_000 });
    const email = form.locator('input[name="user_profile_sensitive[email]"]');
    const original = await email.inputValue();
    await email.fill(`e2e.email.${Date.now().toString(36)}@example.invalid`);
    // Leave currentPassword empty — sensitive change must fail.
    await form.locator('input[name="user_profile_sensitive[currentPassword]"]').fill('');
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/account\/profile/);
    await expect(page.locator('body')).toContainText(/password|contraseña|current/i);
    // Email should not have stuck on the invalid value after failed save (controller reverts).
    await page.goto('/account/profile');
    await dismissProductTour(page);
    await expect(page.locator('input[name="user_profile_sensitive[email]"]')).toHaveValue(original);
  });

  test('password change rejects wrong current password and weak new password (UC-ACC-19)', async ({ page }) => {
    // Do NOT round-trip the demo password: AuthKit "strong" policy rejects `admin123`
    // on restore, which would strand later suites. Assert validation paths instead.
    await page.goto('/account/security');
    await dismissProductTour(page);
    const form = page.locator('form').filter({ has: page.locator('input[name="user_preferences[plainPassword]"]') });
    await expect(form).toBeVisible({ timeout: 15_000 });

    const strong = `E2eStrongPass1!${Date.now().toString(36).slice(-4)}`;
    await form.locator('input[name="user_preferences[currentPassword]"]').fill('definitely-not-the-demo-password');
    await form.locator('input[name="user_preferences[plainPassword]"]').fill(strong);
    await form.locator('input[name="user_preferences[plainPassword_confirm]"]').fill(strong);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/account\/security/);
    await expect(page.locator('body')).toContainText(/password|contraseña|invalid|incorrect|incorrecta|actual/i);

    await page.goto('/account/security');
    await dismissProductTour(page);
    const weakForm = page.locator('form').filter({ has: page.locator('input[name="user_preferences[plainPassword]"]') });
    await weakForm.locator('input[name="user_preferences[currentPassword]"]').fill(DEMO_PASSWORD);
    await weakForm.locator('input[name="user_preferences[plainPassword]"]').fill('short');
    await weakForm.locator('input[name="user_preferences[plainPassword_confirm]"]').fill('short');
    await weakForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/account\/security/);
    await expect(page.locator('body')).toContainText(/password|contraseña|length|fuerte|strong|weak|déb|requirement|requisito/i);
  });

  test('PWA offline document loads (UC-ACC-22)', async ({ page }) => {
    const res = await page.goto('/offline', { waitUntil: 'domcontentloaded' });
    expect(res, 'offline response').not.toBeNull();
    expect(res!.status()).toBeLessThan(400);
    await expect(page.locator('body')).toBeVisible();
  });
});
