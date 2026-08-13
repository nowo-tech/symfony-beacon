import { test, expect } from '@playwright/test';
import {
  dismissCookieConsent,
  dismissProductTour,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

test.describe('Share link access — guest', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('guest hitting invalid share token is sent to login', async ({ page }) => {
    const bogus = 'b'.repeat(64);
    await page.goto(`/share/${bogus}`);
    await dismissCookieConsent(page);
    await expect(page).toHaveURL(/\/login/);
  });
});

test.describe('Share link access — authenticated', () => {
  test('invalid share token does not 5xx', async ({ page }) => {
    const bogus = 'a'.repeat(64);
    const response = await page.goto(`/share/${bogus}`, { waitUntil: 'domcontentloaded' });
    await dismissProductTour(page);
    expect(response?.status() ?? 500).toBeLessThan(500);
    await expect(page).toHaveURL(/\/login|\/dashboard|\/projects\//);
  });

  test('creates a share link and opens it while authenticated', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings`);
    await dismissProductTour(page);

    const share = page.locator('[data-testid="share-links"]');
    await expect(share).toBeVisible();

    const createForm = share.locator('form').filter({ has: page.locator('input[name="project_share_create[days]"]') });
    await createForm.locator('input[name="project_share_create[max_uses]"]').fill('5');
    await createForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings`));

    const shareUrlEl = page.locator('[data-testid="share-url"]');
    await expect(shareUrlEl).toBeVisible({ timeout: 15_000 });
    const shareUrl = (await shareUrlEl.innerText()).trim();
    expect(shareUrl).toMatch(/\/share\/[a-f0-9]{64}/i);

    await page.goto(shareUrl);
    await dismissProductTour(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}`));
    await expect(page).not.toHaveURL(/\/login/);
  });
});
