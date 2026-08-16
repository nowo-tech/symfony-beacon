import { expect, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { dismissCookieConsent, dismissProductTour, waitForPageLoader } from './helpers';

export type ProjectRoleOption = 'viewer' | 'member' | 'admin' | 'full';

/** Create an enabled instance ROLE_USER via Administration (requires admin storageState). */
export async function createEnabledUser(
  page: Page,
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
  const submit = form.locator('button.btn-primary[type="submit"], button[type="submit"]').first();
  await expect(submit).toBeEnabled();
  await Promise.all([
    page.waitForURL((url) => url.pathname.replace(/\/$/, '') === '/admin/users' && !url.searchParams.has('new'), {
      timeout: 20_000,
    }),
    submit.click(),
  ]);
  await waitForPageLoader(page);
  await page.goto(`/admin/users?q=${encodeURIComponent(email)}`);
  await waitForPageLoader(page);
  await expect(page.getByRole('main')).toContainText(email, { timeout: 20_000 });
}

/** Add a direct project membership from Settings → Access (requires members.manage). */
export async function addProjectMember(
  page: Page,
  projectUuid: string,
  email: string,
  role: ProjectRoleOption,
): Promise<void> {
  await page.goto(`/projects/${projectUuid}/settings/access`);
  await dismissProductTour(page);
  const members = page.locator('section.panel').filter({ hasText: /member|miembro/i }).first();
  await members.locator('button[data-action="confirm-dialog#open"]').first().click();
  const addForm = page.locator('form').filter({
    has: page.locator('#member-email, input[name="project_member_add[email]"]'),
  });
  await expect(addForm).toBeVisible({ timeout: 10_000 });
  await addForm.locator('#member-email, input[name="project_member_add[email]"]').fill(email);
  await addForm.locator('#member-add-role, select[name="project_member_add[role]"]').selectOption(role);
  await addForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
  await waitForPageLoader(page);
  await expect(page.getByRole('main')).toContainText(email, { timeout: 15_000 });
}

/** Change an existing direct membership role via the edit-role dialog. */
export async function changeProjectMemberRole(
  page: Page,
  projectUuid: string,
  email: string,
  role: ProjectRoleOption,
): Promise<void> {
  await page.goto(`/projects/${projectUuid}/settings/access`);
  await dismissProductTour(page);
  const row = page.locator('li').filter({ hasText: email }).first();
  await expect(row).toBeVisible({ timeout: 15_000 });
  const editRole = row
    .locator('button[aria-label*="dit role"], button[aria-label*="ditar rol"], button[title*="dit role"]')
    .first();
  await expect(editRole).toBeVisible({ timeout: 10_000 });
  await editRole.click();
  const dialogRole = page.locator('dialog[open] select[name="role"], .confirm-dialog select[name="role"]').last();
  await expect(dialogRole).toBeVisible({ timeout: 10_000 });
  await dialogRole.selectOption(role);
  await page.locator('dialog[open] button.btn-primary, .confirm-dialog button.btn-primary').last().click();
  await waitForPageLoader(page);
}

/** Deactivate a direct membership (inactive rows grant no product access). */
export async function deactivateProjectMember(page: Page, projectUuid: string, email: string): Promise<void> {
  await page.goto(`/projects/${projectUuid}/settings/access`);
  await dismissProductTour(page);
  const row = page.locator('li').filter({ hasText: email }).first();
  await expect(row).toBeVisible({ timeout: 15_000 });
  const deactivate = row
    .locator('button[type="submit"]')
    .filter({ hasText: /deactivate|desactivar|disable/i })
    .first();
  await expect(deactivate).toBeVisible({ timeout: 10_000 });
  await deactivate.click();
  await waitForPageLoader(page);
  await expect(page.getByRole('main')).toContainText(/inactive|inactivo/i);
}

/** Fresh browser context + login as a non-admin user (empty storageState). */
export async function loginAsUser(
  browser: Browser,
  email: string,
  password: string,
): Promise<{ context: BrowserContext; page: Page }> {
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    storageState: { cookies: [], origins: [] },
  });
  const page = await context.newPage();
  await page.goto('/login');
  await dismissCookieConsent(page);
  await page.locator('input[name="login_form[_username]"]').fill(email);
  await page.locator('input[name="login_form[_password]"]').fill(password);
  await page
    .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
    .first()
    .click();
  await page.waitForURL(/\/dashboard/, { timeout: 45_000 });
  await dismissProductTour(page);
  return { context, page };
}

/**
 * Assert a path is refused with branded HTTP 403 (not login redirect).
 * Use for authenticated users who lack ROLE_ADMIN or project.* grants.
 */
export async function expectForbidden(page: Page, path: string): Promise<void> {
  const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
  await dismissProductTour(page);
  expect(response, `No response for ${path}`).not.toBeNull();
  expect(response!.status(), `${path} should be 403`).toBe(403);
  await expect(page, `${path} must not bounce to login`).not.toHaveURL(/\/login(\?|$|\/)/);
  await expect(page.locator('body')).toContainText(/Gate's closed|do not have the keys|403/i);
}

/** Assert authenticated access succeeds (not login, not 403). */
export async function expectAllowed(page: Page, path: string): Promise<void> {
  const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
  await dismissProductTour(page);
  expect(response, `No response for ${path}`).not.toBeNull();
  const status = response!.status();
  expect(status, `${path} returned ${status}`).toBeLessThan(400);
  await expect(page, `${path} must stay authenticated`).not.toHaveURL(/\/login(\?|$|\/)/);
  await expect(page.locator('body')).not.toContainText(/Gate's closed/i);
}
