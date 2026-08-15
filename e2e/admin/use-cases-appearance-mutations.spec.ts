import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, gotoStable, waitForPageLoader } from '../support/helpers';

/**
 * Mutation coverage for Administration → Appearance (UC-ADM-15 / UC-ADM-31)
 * and account display theme preference (UC-ACC-07).
 *
 * Changes real values, asserts persistence, then restores defaults so later suites
 * keep the seeded Beacon look.
 */
test.describe('Appearance mutations', () => {
  test.describe.configure({ mode: 'serial' });

  test('applies a light theme preset and marks it active (UC-ADM-31)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/appearance/themes');
    const ocean = page.locator('button[name="apply_theme"][value="ocean"]');
    await expect(ocean).toBeVisible({ timeout: 15_000 });
    await ocean.click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/appearance\/themes/);
    await expect(page.locator('button[name="apply_theme"][value="ocean"]')).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    // Ocean light accent is #0e7490 — force light chrome so dark-mode vars do not mask it.
    await page.locator('html').evaluate((el) => el.setAttribute('data-theme', 'light'));
    await expect
      .poll(
        async () =>
          (await page.locator('html').evaluate((el) =>
            getComputedStyle(el).getPropertyValue('--beacon-moss').trim(),
          )).toLowerCase(),
        { timeout: 10_000 },
      )
      .toBe('#0e7490');
  });

  test('changes brand name and shows it in the chrome (UC-ADM-31 brand)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/appearance/brand');
    const form = page.locator('[data-testid="appearance-form"]');
    await expect(form).toBeVisible();
    const brand = form.locator('input[name*="[brandName]"]');
    const original = await brand.inputValue();
    const ephemeral = `E2E Brand ${Date.now().toString(36)}`;
    await brand.fill(ephemeral);
    await form.locator('button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('.brand-mark__name').first()).toContainText(ephemeral, { timeout: 15_000 });

    // Restore previous name so other specs keep the seeded brand.
    await gotoStable(page, '/admin/appearance/brand');
    await dismissProductTour(page);
    const formAgain = page.locator('[data-testid="appearance-form"]');
    const brandAgain = formAgain.locator('input[name*="[brandName]"]');
    await brandAgain.fill(original || 'symfony-beacon');
    await formAgain.locator('button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('.brand-mark__name').first()).toContainText(original || 'symfony-beacon', {
      timeout: 15_000,
    });
  });

  test('changes layout corner style and persists (UC-ADM-31 layout)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/appearance/layout');
    const form = page.locator('[data-testid="appearance-form"]');
    await expect(form).toBeVisible();
    const corner = form.locator('select[name*="[cornerStyle]"]');
    await expect(corner).toBeVisible();
    const before = await corner.inputValue();
    const next = before === 'sharp' ? 'rounded' : 'sharp';
    await corner.selectOption(next);
    await form.locator('button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await gotoStable(page, '/admin/appearance/layout');
    await dismissProductTour(page);
    await expect(page.locator('select[name*="[cornerStyle]"]')).toHaveValue(next);

    // Restore.
    await page.locator('select[name*="[cornerStyle]"]').selectOption(before || 'soft');
    await page.locator('[data-testid="appearance-form"] button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
  });

  test('changes accent color, persists, then resets (UC-ADM-15 colors)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/appearance/colors/accents');
    const form = page.locator('[data-testid="appearance-form"]');
    await expect(form).toBeVisible();
    const accent = form.locator('input[name*="[accentColor]"]');
    await expect(accent).toBeVisible();
    await accent.fill('#112233');
    await form.locator('button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await gotoStable(page, '/admin/appearance/colors/accents');
    await dismissProductTour(page);
    await expect(page.locator('input[name*="[accentColor]"]')).toHaveValue('#112233');

    // Reset palette via the section reset control.
    await page.locator('[data-testid="appearance-form"] button[type="submit"][name="reset"]').click();
    await waitForPageLoader(page);
    await gotoStable(page, '/admin/appearance/colors/accents');
    await dismissProductTour(page);
    await expect(page.locator('input[name*="[accentColor]"]')).not.toHaveValue('#112233');
  });

  test('restores beacon light theme preset after mutations (UC-ADM-31)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/appearance/themes');
    const beacon = page.locator('button[name="apply_theme"][value="beacon"]');
    await expect(beacon).toBeVisible();
    await beacon.click();
    await waitForPageLoader(page);
    await expect(page.locator('button[name="apply_theme"][value="beacon"]')).toHaveAttribute(
      'aria-pressed',
      'true',
    );
  });
});

test.describe('Account display theme preference', () => {
  test('preferred theme preference saves and chrome toggle still works (UC-ACC-07)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/display');
    const form = page
      .getByRole('main')
      .locator('form')
      .filter({ has: page.locator('button[type="submit"]') })
      .first();
    await expect(form).toBeVisible();
    const theme = form.locator('select[name*="[preferredTheme]"]');
    await expect(theme).toBeVisible();
    const before = await theme.inputValue();
    const next = before === 'dark' ? 'light' : 'dark';
    await theme.selectOption(next);
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    // WSL can drop the post-submit navigation; re-enter the page via gotoStable.
    await gotoStable(page, '/account/display');
    await dismissProductTour(page);
    await expect(page.locator('select[name*="[preferredTheme]"]')).toHaveValue(next);

    // Chrome toggle still flips data-theme independently of saved preference.
    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);
    const html = page.locator('html');
    const chromeBefore = await html.getAttribute('data-theme');
    await page.locator('[data-theme-toggle]').click();
    await expect
      .poll(async () => html.getAttribute('data-theme'), { timeout: 10_000 })
      .not.toBe(chromeBefore);

    // Restore preference.
    await gotoStable(page, '/account/display');
    await dismissProductTour(page);
    await page.locator('select[name*="[preferredTheme]"]').selectOption(before || 'light');
    await page
      .getByRole('main')
      .locator('form')
      .filter({ has: page.locator('button[type="submit"]') })
      .first()
      .locator('button[type="submit"]')
      .click();
    await waitForPageLoader(page);
  });
});
