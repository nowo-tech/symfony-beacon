import { test, expect } from '@playwright/test';
import {
  beaconAuthHeader,
  createApiKeyAndParseDsn,
  dismissProductTour,
  expectAuthenticatedPage,
  gotoStable,
  ingestHttpBase,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

async function createEphemeralProject(page: import('@playwright/test').Page, name: string): Promise<string> {
  await page.goto('/dashboard?new=1');
  await dismissProductTour(page);
  if (!(await page.locator('input[name="project[name]"]').isVisible().catch(() => false))) {
    await page.locator('[data-action="new-project"]').click();
  }
  await expect(page.locator('input[name="project[name]"]')).toBeVisible({ timeout: 10_000 });
  await page.locator('input[name="project[name]"]').fill(name);
  await page.locator('textarea[name="project[description]"]').fill('Ephemeral Playwright gaps');
  await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();
  await page.waitForURL(/\/projects\/([0-9a-f-]{36})/i, { timeout: 30_000 });
  await dismissProductTour(page);
  const match = page.url().match(/\/projects\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse project UUID from ${page.url()}`);
  }
  return match[1];
}

test.describe('Project / dashboard remaining gaps', () => {
  test('new-project form fragment loads (UC-PROJ-25)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/projects/_new_form');
    await expect(page.locator('input[name="project[name]"]')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('textarea[name="project[description]"]')).toBeVisible();
  });

  test('share link max-uses exhausted rejects (UC-PROJ-23)', async ({ page, browser }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const share = page.locator('[data-testid="share-links"]');
    await expect(share).toBeVisible();
    const createForm = share.locator('form').filter({ has: page.locator('input[name="project_share_create[days]"]') });
    await createForm.locator('input[name="project_share_create[max_uses]"]').fill('1');
    await createForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    const shareUrlEl = page.locator('[data-testid="share-url"]');
    await expect(shareUrlEl).toBeVisible({ timeout: 15_000 });
    const shareUrl = (await shareUrlEl.innerText()).trim();
    const path = shareUrl.replace(/^https?:\/\/[^/]+/i, '');

    // First authenticated consume counts as a use.
    await page.goto(path);
    await dismissProductTour(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}`));

    // Second consume (fresh context, after login) should be rejected / redirected.
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const guest = await ctx.newPage();
    try {
      // Login as demo so share can open (guest hits login first).
      await guest.goto('/login');
      await guest.locator('input[name="login_form[_username]"]').fill(process.env.PLAYWRIGHT_DEMO_EMAIL ?? 'admin@symfony-beacon.local');
      await guest.locator('input[name="login_form[_password]"]').fill(process.env.PLAYWRIGHT_DEMO_PASSWORD ?? 'admin123');
      await guest
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await guest.waitForURL(/\/dashboard/, { timeout: 45_000 });
      await guest.goto(path);
      await waitForPageLoader(guest);
      // Exhausted: flash/error, dashboard redirect, or stay off the project show happily.
      const url = guest.url();
      const body = await guest.locator('body').innerText();
      const exhausted =
        /max|exhaust|agotad|limit|expired|expir|revok|invalid|inválid|no longer|ya no/i.test(body) ||
        !new RegExp(`/projects/${uuid}(/|$)`).test(url) ||
        /\/dashboard|\/login|\/share\//i.test(url);
      expect(exhausted, `url=${url}`).toBeTruthy();
    } finally {
      await ctx.close();
    }
  });

  test('revoked/inactive API key rejected on ingest (UC-PROJ-26)', async ({ page, request }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const label = `e2e-inactive-${Date.now().toString(36)}`;
    const { publicKey, secretKey, projectRef } = await createApiKeyAndParseDsn(page, uuid, label);

    const row = page.locator('li').filter({ hasText: label }).first();
    await expect(row).toBeVisible();
    const revokeBtn = row.locator('form[action*="/revoke"] button[type="submit"]').first();
    page.once('dialog', (d) => d.accept().catch(() => undefined));
    await revokeBtn.click({ force: true });
    await waitForPageLoader(page);

    const base = ingestHttpBase();
    const denied = await request.post(`${base}/api/${projectRef}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(publicKey, secretKey),
      },
      data: '{}\n{}\n{}',
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(denied.status(), await denied.text()).toBe(401);
  });

  test('empty issue list shell on ephemeral project (UC-ISS-26)', async ({ page }) => {
    const uuid = await createEphemeralProject(page, `E2E Empty Issues ${Date.now().toString(36)}`);
    await page.goto(`/projects/${uuid}/issues`);
    await dismissProductTour(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/issues`));
    await expect(page.getByRole('main')).toBeVisible();
    // No issue detail links yet.
    await expect(page.locator(`a[href*="/projects/${uuid}/issues/"][href*="-"]`)).toHaveCount(0);
    await expect(page.locator('body')).toContainText(/no issues|sin issues|empty|vacío|no hay|nothing|ningun/i);
  });

  test('bogus issue status POST is rejected (UC-ISS-27)', async ({ page, request }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }
    await page.goto(`/projects/${uuid}/issues/${issueUuid}`);
    await dismissProductTour(page);
    const token = await page.locator('input[name="issue_status[_token]"]').first().inputValue();
    const res = await request.post(`/projects/${uuid}/issues/${issueUuid}/status`, {
      form: {
        'issue_status[status]': 'not_a_real_status',
        'issue_status[_token]': token,
      },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    // Invalid enum / transition → 4xx (or redirect back with flash); never 5xx.
    expect(res.status(), await res.text()).toBeLessThan(500);
    expect(res.status()).not.toBe(200);
  });

  test('event detail unknown id is 404 (UC-ISS-28)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }
    await gotoStable(page, `/projects/${uuid}/issues/${issueUuid}/events/00000000-0000-4000-8000-000000000000`);
    await expect(page.locator('body')).toContainText(/404|not found|no encontrad|introuvable/i);
  });

  test('environment compare apply opens filtered issue list (UC-ISS-29)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/releases`);
    const form = page.locator('form').filter({
      has: page.locator(
        'select[name="project_release_environment_compare[environment]"], select[name="project_release_environment_compare[compare]"]',
      ),
    });
    if ((await form.count()) === 0) {
      test.skip(true, 'Environment compare form not present (no release environments yet)');
      return;
    }
    const envA = form.locator('select[name="project_release_environment_compare[environment]"]');
    const envB = form.locator('select[name="project_release_environment_compare[compare]"]');
    const aCount = await envA.locator('option:not([value=""])').count();
    const bCount = await envB.locator('option:not([value=""])').count();
    if (aCount === 0 || bCount === 0) {
      test.skip(true, 'No environment options to compare');
      return;
    }
    await envA.selectOption({ index: 1 });
    await envB.selectOption({ index: Math.min(2, bCount) });
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/projects\/.+\/issues/);
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('autocomplete project_member endpoint responds (UC-DASH-14)', async ({ page, request }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    const res = await request.get('/autocomplete/project_member?query=admin', { failOnStatusCode: false });
    expect(res.status(), await res.text()).toBeLessThan(500);
    // UX autocomplete typically returns JSON results or an empty set for authenticated users.
    expect([200, 204, 404]).toContain(res.status());
  });

  test('analytics / performance / releases empty shells on ephemeral project (UC-AN-03 / UC-PERF-04 / UC-REL-03)', async ({
    page,
  }) => {
    const uuid = await createEphemeralProject(page, `E2E Empty Surfaces ${Date.now().toString(36)}`);
    for (const path of [`/projects/${uuid}/analytics`, `/projects/${uuid}/performance`, `/projects/${uuid}/releases`]) {
      await page.goto(path);
      await dismissProductTour(page);
      await expect(page).toHaveURL(new RegExp(path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
      await expect(page.getByRole('main')).toBeVisible();
      await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
    }
    await page.goto(`/projects/${uuid}/performance/transactions/does-not-exist-${Date.now().toString(36)}`);
    await dismissProductTour(page);
    await expect(page.locator('body')).toContainText(/404|not found|no encontrad|introuvable|unknown|desconocid/i);
  });
});
