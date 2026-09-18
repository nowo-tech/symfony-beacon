import { expect, test, type Page } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, gotoStable, waitForPageLoader } from '../support/helpers';

/**
 * Operator legal pages: CKEditor override per locale, public render, restore built-in seed.
 */
test.describe('Admin legal pages', () => {
  test('saves a locale override and restores the built-in page', async ({ page, browser }) => {
    test.setTimeout(90_000);
    const marker = `E2E operator notice ${Date.now().toString(36)}`;
    const editPath = '/admin/legal/notice/en';

    await expectAuthenticatedPage(page, '/admin/legal');
    await expect(page.getByRole('heading', { name: 'Legal pages' })).toBeVisible();
    await expect(page.getByRole('link', { name: /en/ }).first()).toBeVisible();

    try {
      await openEditor(page, editPath);
      await replaceEditorText(page, marker);
      await page.getByRole('button', { name: 'Save legal page' }).click();
      await waitForPageLoader(page);
      await expect(page).toHaveURL(/\/admin\/legal\/notice\/en/);
      await expect(page.locator('.ck-content').first()).toContainText(marker, { timeout: 15_000 });

      const guest = await browser.newContext({
        ignoreHTTPSErrors: true,
        locale: 'en-US',
        storageState: { cookies: [], origins: [] },
      });
      try {
        const publicPage = await guest.newPage();
        await publicPage.goto('/en/legal/notice', { waitUntil: 'domcontentloaded' });
        await expect(publicPage.locator('body')).toContainText(marker);
        await expect(publicPage.locator('body')).not.toContainText('alert(1)');
      } finally {
        await guest.close();
      }
    } finally {
      await restoreBuiltin(page, editPath);
    }

    const guestAfter = await browser.newContext({
      ignoreHTTPSErrors: true,
      locale: 'en-US',
      storageState: { cookies: [], origins: [] },
    });
    try {
      const publicPage = await guestAfter.newPage();
      await publicPage.goto('/en/legal/notice', { waitUntil: 'domcontentloaded' });
      await expect(publicPage.locator('body')).not.toContainText(marker);
      await expect(publicPage.locator('body')).toContainText('Operator legal name');
    } finally {
      await guestAfter.close();
    }
  });
});

async function openEditor(page: Page, editPath: string): Promise<void> {
  await gotoStable(page, editPath);
  await dismissProductTour(page);
  await expect(page.locator('.ck-editor').first()).toBeVisible({ timeout: 20_000 });
}

async function replaceEditorText(page: Page, marker: string): Promise<void> {
  const editable = page.locator('.ck-content').first();
  await editable.click();
  await page.keyboard.press('ControlOrMeta+A');
  await page.keyboard.insertText(marker);
}

async function restoreBuiltin(page: Page, editPath: string): Promise<void> {
  if (!page.url().includes('/admin/legal/notice/en')) {
    await gotoStable(page, editPath).catch(() => undefined);
  }
  const restore = page.getByRole('button', { name: 'Restore built-in text' });
  if (!(await restore.isVisible().catch(() => false))) {
    return;
  }
  await restore.click();
  await waitForPageLoader(page);
}