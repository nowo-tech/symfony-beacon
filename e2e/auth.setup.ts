import { test as setup, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { dismissCookieConsent, dismissProductTour, DEMO_EMAIL, DEMO_PASSWORD } from './helpers';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(__dirname, '.auth', 'admin.json');

function loadDemoCredentials(): { email: string; password: string } {
  const candidates = [
    path.join(__dirname, '..', '.demo-client.env'),
    path.join(__dirname, '.demo-client.env.cache'),
  ];
  for (const envPath of candidates) {
    try {
      if (!fs.existsSync(envPath)) {
        continue;
      }
      const text = fs.readFileSync(envPath, 'utf8');
      const email = text.match(/^BEACON_LOGIN_EMAIL=(.+)$/m)?.[1]?.trim();
      const password = text.match(/^BEACON_LOGIN_PASSWORD=(.+)$/m)?.[1]?.trim();
      // Ignore incomplete pairs (e.g. operator email with empty password from dogfood).
      if (email && password) {
        return { email, password };
      }
    } catch {
      // unreadable — fall through
    }
  }
  return { email: DEMO_EMAIL, password: DEMO_PASSWORD };
}

setup('authenticate as demo admin', async ({ page }) => {
  fs.mkdirSync(path.dirname(authFile), { recursive: true });
  const { email, password } = loadDemoCredentials();

  await page.goto('/login');
  await dismissCookieConsent(page);
  await expect(page.locator('#cookieconsent[data-nowo-open="true"]:not(.hidden)')).toHaveCount(0, {
    timeout: 10_000,
  }).catch(() => undefined);

  await page.locator('input[name="login_form[_username]"]').fill(email);
  await page.locator('input[name="login_form[_password]"]').fill(password);
  await page.locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]').first().click();

  await page.waitForURL(/\/dashboard(\?|$)/, { timeout: 45_000 });
  await dismissProductTour(page);

  await page.context().storageState({ path: authFile });
});
