import { test, expect, type Page } from '@playwright/test';
import {
  dismissCookieConsent,
  dismissProductTour,
  expectAuthenticatedPage,
  gotoStable,
  waitForPageLoader,
} from '../support/helpers';

/**
 * Mutation coverage for chrome theme + UI language (UC-ACC-07 / UC-ACC-10 / UC-ACC-11 / UC-AUTH-13).
 *
 * Changes real theme/locale values, asserts visible effect, then restores English + light
 * so later suites keep the seeded demo chrome.
 *
 * Note: local `.env` often sets DEFAULT_LOCALE=es, so guest default-locale login is bare `/login`.
 */

async function openLocaleSwitcher(page: Page): Promise<void> {
  const details = page.locator('.locale-switcher__details').first();
  await expect(details).toBeVisible({ timeout: 15_000 });
  const open = await details.getAttribute('open');
  if (open === null) {
    await details.locator('summary').click();
  }
}

async function switchAccountLocale(page: Page, locale: string): Promise<void> {
  await openLocaleSwitcher(page);
  const btn = page
    .locator(
      `form[action="/account/locale/${locale}"] button[type="submit"], form[action*="/account/locale/${locale}"] button`,
    )
    .first();
  await expect(btn).toBeVisible({ timeout: 10_000 });
  await btn.click();
  await waitForPageLoader(page);
  await dismissProductTour(page);
  // WSL can land on a blank document; re-enter a stable authenticated page.
  const lang = await page.locator('html').getAttribute('lang');
  if (!lang || !new RegExp(locale, 'i').test(lang)) {
    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);
  }
  await expect(page.locator('html')).toHaveAttribute('lang', new RegExp(locale, 'i'));
}

async function switchGuestPathLocale(page: Page, locale: string): Promise<void> {
  await openLocaleSwitcher(page);
  const link = page.locator(`.locale-switcher a[hreflang="${locale}"]`).first();
  if (await link.isVisible().catch(() => false)) {
    await link.click();
    await waitForPageLoader(page);
    await dismissCookieConsent(page);
    return;
  }
  const btn = page
    .locator(`form[action="/locale/${locale}"] button[type="submit"], form[action*="/locale/${locale}"] button`)
    .first();
  await expect(btn).toBeVisible({ timeout: 10_000 });
  await btn.click();
  await waitForPageLoader(page);
  await dismissCookieConsent(page);
}

async function setPreferredTheme(page: Page, value: 'light' | 'dark'): Promise<void> {
  await gotoStable(page, '/account/display');
  await dismissProductTour(page);
  const form = page
    .getByRole('main')
    .locator('form')
    .filter({ has: page.locator('button[type="submit"]') })
    .first();
  const theme = form.locator('select[name*="[preferredTheme]"]');
  await expect(theme).toBeVisible();
  await theme.selectOption(value);
  await form.locator('button[type="submit"]').click();
  await waitForPageLoader(page);
  await gotoStable(page, '/account/display');
  await dismissProductTour(page);
  await expect(page.locator('select[name*="[preferredTheme]"]')).toHaveValue(value);
}

async function toggleThemeAndWaitForSync(page: Page, expected: 'light' | 'dark'): Promise<void> {
  const html = page.locator('html');
  const sync = page.waitForResponse(
    (res) =>
      res.url().includes('/account/theme') &&
      res.request().method() === 'POST' &&
      res.ok(),
    { timeout: 15_000 },
  );
  await page.locator('[data-theme-toggle]').click();
  await sync;
  await expect.poll(async () => html.getAttribute('data-theme'), { timeout: 10_000 }).toBe(expected);
}

test.describe('Theme and locale mutations', () => {
  test.describe.configure({ mode: 'serial' });

  test('chrome theme toggle syncs and survives navigation (UC-ACC-11)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    const html = page.locator('html');
    const toggle = page.locator('[data-theme-toggle]');
    await expect(toggle).toBeVisible();

    // Normalize to light via sync so SSR preference matches.
    if ((await html.getAttribute('data-theme')) === 'dark') {
      await toggleThemeAndWaitForSync(page, 'light');
    }

    await toggleThemeAndWaitForSync(page, 'dark');

    await gotoStable(page, '/account/profile');
    await dismissProductTour(page);
    await expect(html).toHaveAttribute('data-theme', 'dark');

    await toggleThemeAndWaitForSync(page, 'light');
    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);
    await expect(html).toHaveAttribute('data-theme', 'light');
  });

  test('preferred theme preference applies on next load (UC-ACC-07)', async ({ page }) => {
    await setPreferredTheme(page, 'dark');
    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

    await setPreferredTheme(page, 'light');
    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  });

  test('authenticated locale switch changes html lang and UI copy (UC-ACC-10)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');

    await switchAccountLocale(page, 'es');
    await expect(page.locator('body')).toContainText(/Proyectos|Panel/i);
    await expect(page.locator('.locale-switcher__code').first()).toHaveText(/ES/i);

    await switchAccountLocale(page, 'de');
    await expect(page.locator('body')).toContainText(/Projekte|Armaturenbrett|Dashboard/i);
    await expect(page.locator('.locale-switcher__code').first()).toHaveText(/DE/i);

    await switchAccountLocale(page, 'en');
    await expect(page.locator('body')).toContainText(/Projects|Dashboard/i);
    await expect(page.locator('.locale-switcher__code').first()).toHaveText(/EN/i);
  });

  test('theme and language can both change in one session (UC-ACC-07 / UC-ACC-10 / UC-ACC-11)', async ({
    page,
  }) => {
    await expectAuthenticatedPage(page, '/dashboard');

    await switchAccountLocale(page, 'es');
    await expect(page.locator('html')).toHaveAttribute('lang', /es/i);

    await toggleThemeAndWaitForSync(page, 'dark');

    await gotoStable(page, '/admin/appearance');
    await dismissProductTour(page);
    await expect(page.locator('html')).toHaveAttribute('lang', /es/i);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.getByRole('main')).toContainText(/Apariencia/i);

    // Restore English + light chrome for later suites.
    await switchAccountLocale(page, 'en');
    await expect(page.locator('html')).toHaveAttribute('lang', /en/i);
    if ((await page.locator('html').getAttribute('data-theme')) !== 'light') {
      await toggleThemeAndWaitForSync(page, 'light');
    }
    await setPreferredTheme(page, 'light');
  });
});

test.describe('Guest locale path mutations', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('guest path locale switch changes login language (UC-AUTH-13)', async ({ page }) => {
    // Start on English (non-default when DEFAULT_LOCALE=es).
    await page.goto('/en/login');
    await dismissCookieConsent(page);
    await expect(page.locator('html')).toHaveAttribute('lang', /en/i);
    await expect(page.getByRole('heading', { name: /Sign in/i })).toBeVisible();

    // Default locale (es) uses bare /login with unlocalized: serve.
    await switchGuestPathLocale(page, 'es');
    await expect(page).toHaveURL(/\/(es\/)?login/);
    await expect(page.locator('html')).toHaveAttribute('lang', /es/i);
    await expect(page.getByRole('heading', { name: /Iniciar sesión/i })).toBeVisible();

    await switchGuestPathLocale(page, 'de');
    await expect(page).toHaveURL(/\/de\/login/);
    await expect(page.locator('html')).toHaveAttribute('lang', /de/i);
    await expect(page.getByRole('heading', { name: /Anmelden/i })).toBeVisible();

    await switchGuestPathLocale(page, 'en');
    await expect(page).toHaveURL(/\/en\/login/);
    await expect(page.locator('html')).toHaveAttribute('lang', /en/i);
    await expect(page.getByRole('heading', { name: /Sign in/i })).toBeVisible();
  });
});
