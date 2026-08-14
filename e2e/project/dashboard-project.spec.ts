import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, loginAsDemo, logout, requireSampleOrSkip, resolveDemoProjectUuid } from '../support/helpers';

test.describe('Dashboard & navigation', () => {
  test('dashboard loads with project list', async ({ page }) => {
    await page.goto('/dashboard');
    await dismissProductTour(page);
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.locator('[data-tour="user-menu"], [data-user-menu]')).toBeVisible();
    const projectLink = page.locator('a[href*="/projects/"]').filter({ hasNot: page.locator('[href*="/projects/new"]') });
    await expect(projectLink.first()).toBeVisible();
  });

  test('dashboard aside panel routes load', async ({ page }) => {
    for (const path of [
      '/dashboard/summary',
      '/dashboard/assignments',
      '/dashboard/mentions',
      '/dashboard/activity',
      '/dashboard/alerts',
      '/dashboard/new-in-release',
    ]) {
      await expectAuthenticatedPage(page, path);
    }
  });

  test('user menu links work', async ({ page }) => {
    await page.goto('/dashboard');
    await dismissProductTour(page);
    const menu = page.locator('[data-tour="user-menu"], [data-user-menu]');
    await menu.locator('summary').click();
    await expect(menu.locator('a[href*="/account"]')).toBeVisible();
    await expect(menu.locator('[data-tour="admin-link"], a[href*="/admin"]')).toBeVisible();
  });
});

// Own login — must NOT use shared storageState (logout invalidates the PHP session id).
test.describe('Logout flow', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('logout returns to login', async ({ page }) => {
    await loginAsDemo(page);
    await logout(page);
    await expect(page.locator('input[name="login_form[_username]"]')).toBeVisible();
  });
});

test.describe('Project surfaces', () => {
  test('project show, issues, analytics, performance, releases, settings', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);

    for (const path of [
      `/projects/${uuid}`,
      `/projects/${uuid}/issues`,
      `/projects/${uuid}/analytics`,
      `/projects/${uuid}/performance`,
      `/projects/${uuid}/performance?nplus1=1`,
      `/projects/${uuid}/releases`,
      `/projects/${uuid}/settings`,
      `/projects/${uuid}/notifications/help`,
      `/projects/${uuid}/notifications/new`,
      `/projects/${uuid}/threshold-rules/new`,
    ]) {
      await expectAuthenticatedPage(page, path);
    }
  });

  test('new project form loads', async ({ page }) => {
    await expectAuthenticatedPage(page, '/projects/new');
    await expect(page.getByRole('main')).toBeVisible();
  });

  test('issue list can open first issue when present', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/issues`);
    await dismissProductTour(page);

    const issueLink = page.locator(`a[href*="/projects/${uuid}/issues/"]`).first();
    if ((await issueLink.count()) === 0) {
      requireSampleOrSkip(false, 'No issues seeded — run make seed-sample');
      return;
    }

    await issueLink.click();
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/issues/[0-9a-f-]{36}`, 'i'));
    await expect(page.locator('body')).toBeVisible();
  });

  test('issue list accepts search and sort query params', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/issues?q=error&sort=last_seen&dir=desc&page=1`);
  });

  test('export endpoints respond for project owner', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    for (const path of [
      `/projects/${uuid}/export/issues.csv`,
      `/projects/${uuid}/export/issues.json`,
      `/projects/${uuid}/export/events.csv`,
      `/projects/${uuid}/export/events.json`,
      `/projects/${uuid}/config/export`,
    ]) {
      const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 15_000 }).catch(() => null),
        page.goto(path, { waitUntil: 'commit' }).catch((err: Error) => {
          if (!/Download is starting/i.test(err.message)) {
            throw err;
          }
          return null;
        }),
      ]);

      if (download) {
        expect(download.suggestedFilename().length, path).toBeGreaterThan(0);
        continue;
      }
      await expect(page, path).not.toHaveURL(/\/login/);
    }
  });

  test('project show overview renders for demo project', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}`);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.locator('[data-tour="project-nav"]')).toBeVisible();
  });
});

