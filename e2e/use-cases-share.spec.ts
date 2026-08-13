import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

test.describe('Share links — guest consume & scoped (UC-PROJ-10/11/12)', () => {
  test('unauthenticated share open redirects to login (UC-PROJ-12)', async ({ page, request }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);

    const share = page.locator('[data-testid="share-links"]');
    await expect(share).toBeVisible();
    const createForm = share.locator('form').filter({ has: page.locator('input[name="project_share_create[days]"]') });
    await createForm.locator('input[name="project_share_create[max_uses]"]').fill('3');
    await createForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    const shareUrlEl = page.locator('[data-testid="share-url"]');
    await expect(shareUrlEl).toBeVisible({ timeout: 15_000 });
    const shareUrl = (await shareUrlEl.innerText()).trim();
    const path = shareUrl.replace(/^https?:\/\/[^/]+/i, '');

    // Use request without cookies (APIRequestContext from the fixture still may carry storage —
    // create an isolated one).
    const anon = await page.context().browser()!.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const anonReq = anon.request;
    const res = await anonReq.get(path, { maxRedirects: 0 });
    expect([301, 302, 303, 307, 308]).toContain(res.status());
    const location = res.headers().location ?? '';
    expect(location).toMatch(/login/i);
    await anon.close();

    // Authenticated consume still works (owner session).
    await page.goto(path);
    await dismissProductTour(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}`));
    await expect(page).not.toHaveURL(/\/login/);
    void request;
  });

  test('creates issue-scoped share link (UC-PROJ-10)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const share = page.locator('[data-testid="share-links"]');
    const createForm = share.locator('form').filter({ has: page.locator('input[name="project_share_create[days]"]') });
    await createForm.locator('input[name="project_share_create[issue_uuid]"]').fill(issueUuid);
    await createForm.locator('input[name="project_share_create[max_uses]"]').fill('2');
    await createForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('[data-testid="share-url"]')).toBeVisible({ timeout: 15_000 });
    await expect(share).toContainText(/scoped|issue|acotad/i);
  });

  test('revokes the newest share link (UC-PROJ-11)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const share = page.locator('[data-testid="share-links"]');
    let revoke = share.locator('form[action*="/share-links/"][action*="/revoke"] button[type="submit"]').first();
    if ((await revoke.count()) === 0) {
      const createForm = share.locator('form').filter({ has: page.locator('input[name="project_share_create[days]"]') });
      await createForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
      revoke = share.locator('form[action*="/share-links/"][action*="/revoke"] button[type="submit"]').first();
    }
    await expect(revoke).toBeVisible({ timeout: 15_000 });
    await revoke.click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/access`));
    await expect(page).not.toHaveURL(/\/login/);
  });
});
