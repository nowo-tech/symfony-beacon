import { expect, test } from '@playwright/test';
import { dismissCookieConsent, dismissProductTour, expectAuthenticatedPage, resolveDemoProjectUuid } from '../support/helpers';
import {
  assertHtmlTwigStandardization,
  readResponseHtml,
} from '../support/html-markup';
import { assertPageTabGeometry } from '../support/tab-geometry';

/**
 * UC-UI-13 — host Twig HTML is a real document shell with CSP nonces on inline style/script.
 * UC-UI-14 — section tabs share tags/classes AND the same row y/height (no bold-box jump).
 */

test.describe('HTML / Twig markup standardization — public', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('public auth and legal shells ship CSP-safe Twig markup', async ({ page }) => {
    for (const path of ['/login', '/en/login', '/reset-password', '/en/legal/notice']) {
      const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(response, `GET ${path}`).not.toBeNull();
      expect(response!.status(), `GET ${path} status`).toBeLessThan(400);
      const html = await readResponseHtml(response);
      await dismissCookieConsent(page);
      await assertHtmlTwigStandardization(page, html);
    }
  });
});

test.describe('HTML / Twig markup + tab geometry — authenticated', () => {
  test('account preference tabs share markup and a pixel lock', async ({ page }) => {
    const paths = [
      '/account/profile',
      '/account/projects',
      '/account/groups',
      '/account/privacy',
      '/account/security',
      '/account/display',
    ];
    for (const path of paths) {
      const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(response, `GET ${path}`).not.toBeNull();
      expect(response!.status(), `GET ${path} status`).toBeLessThan(400);
      const html = await readResponseHtml(response);
      await dismissProductTour(page);
      await assertHtmlTwigStandardization(page, html);
      await assertPageTabGeometry(page);
    }
  });

  test('admin appearance and project issues tabs share the same lock', async ({ page }) => {
    const appearance = await page.goto('/admin/appearance', { waitUntil: 'domcontentloaded' });
    expect(appearance, 'GET /admin/appearance').not.toBeNull();
    expect(appearance!.status()).toBeLessThan(400);
    await dismissProductTour(page);
    await assertHtmlTwigStandardization(page, await readResponseHtml(appearance));
    await assertPageTabGeometry(page);

    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/issues`);
    await dismissProductTour(page);
    await assertPageTabGeometry(page);

    await expectAuthenticatedPage(page, `/projects/${uuid}/settings`);
    await dismissProductTour(page);
    await assertPageTabGeometry(page);
  });

  test('kit admin section tabs keep the same row after the active pill moves', async ({ page }) => {
    for (const path of ['/admin/cookie-consent', '/admin/maintenance', '/admin/appearance']) {
      const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(response, `GET ${path}`).not.toBeNull();
      expect(response!.status(), `GET ${path} status`).toBeLessThan(400);
      await dismissProductTour(page);
      await assertHtmlTwigStandardization(page, await readResponseHtml(response));
      await assertPageTabGeometry(page);

      const items = page.locator('.beacon-tabs a.nowo-ui-tabs__item, .kit-admin a.nowo-ui-tabs__item, [role="tab"]');
      const count = await items.count();
      if (count < 2) {
        continue;
      }
      const next = items.nth(1);
      if (await next.isVisible().catch(() => false)) {
        await next.click();
        await page.waitForLoadState('domcontentloaded');
        await assertPageTabGeometry(page);
      }
    }
  });
});

