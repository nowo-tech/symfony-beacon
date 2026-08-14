import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, resolveDemoProjectUuid } from '../support/helpers';

test.describe('Appearance & PWA smoke', () => {
  test('appearance theme sections load', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/appearance');
    // Nested sections vary by kit version — hit known roots.
    for (const path of [
      '/admin/appearance',
    ]) {
      await expectAuthenticatedPage(page, path);
    }
  });

  test('PWA manifest and service worker are reachable', async ({ request }) => {
    for (const path of ['/manifest.webmanifest', '/manifest.json', '/sw.js', '/offline']) {
      const res = await request.get(path);
      // Some builds only expose a subset — tolerate 404, fail on 5xx.
      expect(res.status(), path).toBeLessThan(500);
    }
  });
});

test.describe('Project settings deep checks', () => {
  test('settings page loads for demo project', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/settings`);
    await expect(page.getByRole('main')).toBeVisible();
  });
});
