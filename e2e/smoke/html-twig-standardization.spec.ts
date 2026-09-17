import { expect, test } from '@playwright/test';
import { dismissCookieConsent } from '../support/helpers';
import {
  assertHtmlTwigStandardization,
  readResponseHtml,
} from '../support/html-markup';

/**
 * UC-UI-13 — host Twig HTML is a real document shell with CSP nonces on inline style/script.
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
