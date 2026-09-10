import { test, expect, type Page } from '@playwright/test';
import {
  dismissCookieConsent,
  dismissProductTour,
  gotoStable,
  logout,
  waitForPageLoader,
} from '../support/helpers';
import { createEnabledUser, loginAsUser } from '../support/security';
import { expectFrankenPhpRuntime, frankenPhpExpectationFromEnv } from '../support/frankenphp';

/**
 * FrankenPHP HTTP worker-safety (shared Kernel, RESET_KERNEL=false, single worker process).
 *
 * This is NOT product-catalog coverage. Run via:
 *   make test-e2e-worker-safe
 * Classic contrast:
 *   make test-e2e-worker-safe-classic
 *
 * Requires isolated stack (`PLAYWRIGHT_ISOLATED=1`) with FRANKENPHP_WORKER_NUM=1 so
 * parallel Playwright contexts hit the same PHP worker process.
 */
test.describe('FrankenPHP worker kernel isolation', () => {
  test.describe.configure({ mode: 'serial' });

  test('runtime probe matches worker-safe contract', async ({ request }) => {
    await expectFrankenPhpRuntime(request, frankenPhpExpectationFromEnv());
  });

  test('request locale does not stick across browser contexts', async ({ page, browser }) => {
    const expectMode = frankenPhpExpectationFromEnv();
    test.skip(expectMode.mode !== 'worker', 'Locale leak check targets shared Kernel (worker mode)');

    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);

    // Switch admin UI to German (unlikely DEFAULT_LOCALE) so a sticky Request locale is obvious.
    const details = page.locator('.locale-switcher__details').first();
    await expect(details).toBeVisible({ timeout: 15_000 });
    if ((await details.getAttribute('open')) === null) {
      await details.locator('summary').click();
    }
    const deBtn = page
      .locator('form[action="/account/locale/de"] button[type="submit"], form[action*="/account/locale/de"] button')
      .first();
    await expect(deBtn).toBeVisible({ timeout: 10_000 });
    await deBtn.click();
    await waitForPageLoader(page);
    await dismissProductTour(page);
    if (!/de/i.test((await page.locator('html').getAttribute('lang')) ?? '')) {
      await gotoStable(page, '/dashboard');
      await dismissProductTour(page);
    }
    await expect(page.locator('html')).toHaveAttribute('lang', /de/i);

    // Fresh guest context + explicit EN path — must not inherit admin `de` from a leaked Kernel locale.
    const guest = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
      locale: 'en-US',
    });
    try {
      const guestPage = await guest.newPage();
      await guestPage.goto('/en/login');
      await dismissCookieConsent(guestPage);
      await expect(guestPage.locator('html')).toHaveAttribute('lang', /en/i);
      await expect(guestPage.locator('html')).not.toHaveAttribute('lang', /de/i);
    } finally {
      await guest.close();
    }

    // Restore English chrome for later suites sharing this storageState.
    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);
    if ((await page.locator('.locale-switcher__details').first().getAttribute('open')) === null) {
      await page.locator('.locale-switcher__details summary').first().click();
    }
    const enBtn = page
      .locator('form[action="/account/locale/en"] button[type="submit"], form[action*="/account/locale/en"] button')
      .first();
    if (await enBtn.isVisible().catch(() => false)) {
      await enBtn.click();
      await waitForPageLoader(page);
    }
  });

  test('theme preference does not leak to a parallel guest context', async ({ page, browser }) => {
    const expectMode = frankenPhpExpectationFromEnv();
    test.skip(expectMode.mode !== 'worker', 'Theme leak check targets shared Kernel (worker mode)');

    await gotoStable(page, '/dashboard');
    await dismissProductTour(page);
    const html = page.locator('html');
    const toggle = page.locator('[data-theme-toggle]');
    await expect(toggle).toBeVisible();

    if ((await html.getAttribute('data-theme')) === 'dark') {
      await toggleTheme(page, 'light');
    }
    await toggleTheme(page, 'dark');
    await expect(html).toHaveAttribute('data-theme', 'dark');

    const guest = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    try {
      const guestPage = await guest.newPage();
      await guestPage.goto('/login');
      await dismissCookieConsent(guestPage);
      // Guest must not inherit admin dark theme from a leaked Kernel/service cache.
      const guestTheme = await guestPage.locator('html').getAttribute('data-theme');
      expect(guestTheme === 'dark').toBeFalsy();
    } finally {
      await guest.close();
    }

    await toggleTheme(page, 'light');
  });

  test('logout clears auth for follow-up anonymous requests on the same worker', async ({
    page,
    browser,
  }) => {
    const expectMode = frankenPhpExpectationFromEnv();
    test.skip(expectMode.mode !== 'worker', 'Auth isolation targets shared Kernel (worker mode)');

    const suffix = Date.now().toString(36);
    const email = `worker-logout-${suffix}@example.test`;
    const password = `WkOut!${suffix}`;
    await createEnabledUser(page, email, password, `Worker Out ${suffix}`);

    const { context, page: user } = await loginAsUser(browser, email, password);
    try {
      await gotoStable(user, '/dashboard');
      await expect(user).toHaveURL(/\/dashboard/);

      // AuthKit logout needs CSRF — use chrome menu link (not bare GET /logout).
      await logout(user);
      await expect(user).toHaveURL(/\/login/);

      await user.goto('/dashboard', { waitUntil: 'domcontentloaded' });
      await expect(user).toHaveURL(/\/login/);
    } finally {
      await context.close();
    }
  });

  test('CSRF token from one session is rejected in another context', async ({ page, browser }) => {
    const expectMode = frankenPhpExpectationFromEnv();
    test.skip(expectMode.mode !== 'worker', 'CSRF isolation targets shared Kernel (worker mode)');

    await gotoStable(page, '/account/display');
    await dismissProductTour(page);
    const form = page
      .getByRole('main')
      .locator('form')
      .filter({ has: page.locator('button[type="submit"]') })
      .first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    const tokenInput = form.locator('input[name="account_display[_token]"], input[type="hidden"][name*="_token"]').first();
    await expect(tokenInput).toBeAttached({ timeout: 10_000 });
    const token = await tokenInput.inputValue();
    expect(token.length).toBeGreaterThan(8);

    const guest = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    try {
      const guestPage = await guest.newPage();
      const res = await guestPage.request.post('/account/display', {
        form: {
          'account_display[preferredTheme]': 'dark',
          'account_display[_token]': token,
        },
        failOnStatusCode: false,
        maxRedirects: 0,
      });
      // Guest must not successfully mutate admin preferences with a stolen token.
      expect([302, 303, 400, 401, 403, 422]).toContain(res.status());
      await guestPage.goto('/login');
      await dismissCookieConsent(guestPage);
      await expect(guestPage).toHaveURL(/\/login/);
    } finally {
      await guest.close();
    }
  });
});

async function toggleTheme(page: Page, expected: 'light' | 'dark'): Promise<void> {
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
