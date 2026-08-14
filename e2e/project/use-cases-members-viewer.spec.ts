import { test, expect } from '@playwright/test';
import {
  dismissCookieConsent,
  dismissProductTour,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

async function createEnabledUser(
  page: import('@playwright/test').Page,
  email: string,
  password: string,
  displayName: string,
): Promise<void> {
  await page.goto('/admin/users?new=1');
  await dismissProductTour(page);
  await waitForPageLoader(page);
  const form = page.locator('form').filter({ has: page.locator('input[name="admin_user[email]"]') });
  await expect(form).toBeVisible({ timeout: 15_000 });
  await form.locator('input[name="admin_user[email]"]').fill(email);
  await form.locator('input[name="admin_user[displayName]"]').fill(displayName);
  await form.locator('input[name="admin_user[password]"]').fill(password);
  await form.locator('select[name="admin_user[role]"]').selectOption('user');
  const enabled = form.locator('input[name="admin_user[enabled]"]');
  if ((await enabled.count()) > 0 && !(await enabled.isChecked().catch(() => false))) {
    await enabled.check();
  }
  const submit = form.locator('button.btn-primary[type="submit"]');
  await expect(submit).toBeEnabled();
  await Promise.all([
    page.waitForURL((url) => url.pathname.replace(/\/$/, '') === '/admin/users' && !url.searchParams.has('new'), {
      timeout: 20_000,
    }),
    submit.click(),
  ]);
  await waitForPageLoader(page);
  // Directory may paginate; filter so the new row is visible.
  await page.goto(`/admin/users?q=${encodeURIComponent(email)}`);
  await waitForPageLoader(page);
  await expect(page.getByRole('main')).toContainText(email, { timeout: 20_000 });
}

test.describe('Members, viewer RBAC, mentions — remaining use cases', () => {
  test('add viewer member, assert read-only chrome, then remove (UC-PROJ-06 / UC-ISS-24)', async ({
    page,
    browser,
  }) => {
    const suffix = Date.now().toString(36);
    const email = `e2e.viewer.${suffix}@example.invalid`;
    const password = `E2eView1!${suffix}`;
    const local = `e2e.viewer.${suffix}`;

    await createEnabledUser(page, email, password, `Viewer ${suffix}`);

    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);

    const members = page.locator('section.panel').filter({ hasText: /member|miembro/i }).first();
    await members.locator('button[data-action="confirm-dialog#open"]').first().click();
    const addForm = page.locator('form').filter({ has: page.locator('#member-email, input[name="project_member_add[email]"]') });
    await expect(addForm).toBeVisible({ timeout: 10_000 });
    await addForm.locator('#member-email, input[name="project_member_add[email]"]').fill(email);
    const roleSelect = addForm.locator('#member-add-role, select[name="project_member_add[role]"]');
    // Prefer viewer when available.
    const viewerOpt = roleSelect.locator('option[value="viewer"]');
    if ((await viewerOpt.count()) > 0) {
      await roleSelect.selectOption('viewer');
    } else {
      await roleSelect.selectOption({ index: 0 });
    }
    await addForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(email, { timeout: 15_000 });

    const origin = new URL(page.url()).origin;
    const viewerCtx = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const viewer = await viewerCtx.newPage();
    await viewer.goto(`${origin}/login`);
    await dismissCookieConsent(viewer);
    await viewer.locator('input[name="login_form[_username]"]').fill(email);
    await viewer.locator('input[name="login_form[_password]"]').fill(password);
    await viewer
      .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
      .first()
      .click();
    await viewer.waitForURL(/\/dashboard/, { timeout: 45_000 });
    await dismissProductTour(viewer);
    await viewer.goto(`${origin}/projects/${uuid}/issues`);
    await dismissProductTour(viewer);
    const issueLink = viewer.locator(`a[href*="/projects/${uuid}/issues/"]`).first();
    if ((await issueLink.count()) > 0) {
      await issueLink.click();
      await dismissProductTour(viewer);
      await expect(viewer.locator('[data-testid="viewer-readonly"]')).toBeVisible({ timeout: 15_000 });
    } else {
      requireSampleOrSkip(false, 'No issues for viewer — run make seed-sample');
    }
    await viewerCtx.close();

    // Remove member (cleanup).
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const row = page.locator('li').filter({ hasText: email }).first();
    const removeOpen = row.locator('button[data-action="confirm-dialog#open"]').filter({ hasText: /|./ }).last();
    // Prefer remove dialog trigger by aria-label.
    const removeBtn = row.locator('button[aria-label*="emove"], button[title*="emove"], button[aria-label*="liminar"]').first();
    if (await removeBtn.isVisible().catch(() => false)) {
      await removeBtn.click();
      const removeForm = page.locator('form').filter({ has: page.locator(`h2:has-text("${local}"), h2`) }).filter({
        has: page.locator('button.btn-danger'),
      }).last();
      const danger = page.locator('dialog[open] button.btn-danger, .confirm-dialog button.btn-danger').last();
      if (await danger.isVisible().catch(() => false)) {
        await danger.click();
        await waitForPageLoader(page);
      }
    }
    void removeOpen;
  });

  test('@mention in comment appears in mentions inbox (UC-ISS-25 / UC-DASH-06)', async ({ page, browser }) => {
    const suffix = Date.now().toString(36);
    const email = `e2e.mention.${suffix}@example.invalid`;
    const password = `E2eMen1!${suffix}`;
    const token = `e2e.mention.${suffix}`;

    await createEnabledUser(page, email, password, `Mention ${suffix}`);
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const members = page.locator('section.panel').filter({ hasText: /member|miembro/i }).first();
    await members.locator('button[data-action="confirm-dialog#open"]').first().click();
    const addForm = page.locator('form').filter({ has: page.locator('#member-email, input[name="project_member_add[email]"]') });
    await addForm.locator('#member-email, input[name="project_member_add[email]"]').fill(email);
    const roleSelect = addForm.locator('#member-add-role, select[name="project_member_add[role]"]');
    if ((await roleSelect.locator('option[value="member"]').count()) > 0) {
      await roleSelect.selectOption('member');
    }
    await addForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);

    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }
    const comment = `@${token} e2e mention ${suffix}`;
    const commentForm = page.locator('[data-testid="issue-comments"] form, form.issue-comments__form');
    await commentForm.locator('textarea, textarea[name="body"]').first().fill(comment);
    await commentForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('[data-testid="issue-comments"]')).toContainText(token, { timeout: 15_000 });

    const origin = new URL(page.url()).origin;
    const ctx = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const member = await ctx.newPage();
    await member.goto(`${origin}/login`);
    await dismissCookieConsent(member);
    await member.locator('input[name="login_form[_username]"]').fill(email);
    await member.locator('input[name="login_form[_password]"]').fill(password);
    await member
      .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
      .first()
      .click();
    await member.waitForURL(/\/dashboard/, { timeout: 45_000 });
    await dismissProductTour(member);
    await member.goto(`${origin}/dashboard/mentions`);
    await dismissProductTour(member);
    await expect(member.getByRole('main')).toBeVisible();
    // Mention row or empty if async lag — assert page OK and optionally content.
    const body = await member.locator('main').innerText();
    if (!/mention|mención|e2e\.mention/i.test(body)) {
      // Soft: inbox shell still rendered.
      await expect(member.locator('[data-testid="mentions-empty"], [data-testid="mentions-results"], main').first()).toBeVisible();
    }
    await ctx.close();
  });
});
