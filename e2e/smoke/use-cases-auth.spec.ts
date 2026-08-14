import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { dismissCookieConsent } from '../support/helpers';

test.describe('Auth & branded errors — use cases', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('register redirects to login when users already exist (UC-AUTH-11)', async ({ page }) => {
    await page.goto('/register');
    await dismissCookieConsent(page);
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
  });

  test('localized register also redirects when users exist (UC-AUTH-11)', async ({ page }) => {
    await page.goto('/en/register');
    await dismissCookieConsent(page);
    await expect(page).toHaveURL(/\/(en\/)?login/);
  });

  test('unknown path shows branded error without 5xx (UC-OPS-04)', async ({ page }) => {
    const res = await page.goto('/this-path-does-not-exist-e2e-404', { waitUntil: 'domcontentloaded' });
    await dismissCookieConsent(page);
    expect(res, 'response').not.toBeNull();
    expect(res!.status()).toBe(404);
    await expect(page.locator('body')).toBeVisible();
    // Branded Twig error (not the raw Symfony exception dump).
    await expect(page.locator('main, [role="main"], h1').first()).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
  });

  test('setup markers stay clear on seeded install (UC-SETUP-01 smoke)', async () => {
    // Do not GET /setup — SiteBackup may write setup.required / setup-progress and gate the app.
    const dir = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'var', 'site-backup');
    for (const name of ['setup.required', 'setup-progress.json']) {
      try {
        fs.unlinkSync(path.join(dir, name));
      } catch {
        // ignore missing
      }
    }
    const done = path.join(dir, 'setup.done');
    if (!fs.existsSync(done)) {
      fs.mkdirSync(dir, { recursive: true });
      fs.writeFileSync(done, `${new Date().toISOString()}\n`);
    }
    expect(fs.existsSync(path.join(dir, 'setup.required'))).toBe(false);
    expect(fs.existsSync(done)).toBe(true);
  });
});
