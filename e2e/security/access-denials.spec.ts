import { test, expect } from '@playwright/test';
import { openFirstIssue, requireSampleOrSkip, resolveDemoProjectUuid } from '../support/helpers';
import {
  addProjectMember,
  changeProjectMemberRole,
  createEnabledUser,
  deactivateProjectMember,
  expectAllowed,
  expectForbidden,
  loginAsUser,
} from '../support/security';

/**
 * Security denials — prove missing ROLE_ADMIN / project.* grants block HTTP access.
 * Positive chrome for owners lives elsewhere; this suite asserts 403 / login gates.
 */
test.describe('Security — access denials (instance + project RBAC)', () => {
  test('guest is redirected to login on protected product routes (UC-SEC-01)', async ({ browser }) => {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    try {
      const uuid = '00000000-0000-4000-8000-000000000001';
      for (const path of [
        '/dashboard',
        '/admin',
        '/admin/users',
        '/account',
        `/projects/${uuid}/issues`,
        `/projects/${uuid}/settings/general`,
      ]) {
        await page.goto(path);
        await expect(page, path).toHaveURL(/\/login/);
      }
    } finally {
      await context.close();
    }
  });

  test('ROLE_USER without ROLE_ADMIN cannot open Administration (UC-SEC-02)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.sec.noadmin.${suffix}@example.invalid`;
    const password = `E2eSecAdm1!${suffix}`;

    await createEnabledUser(page, email, password, `Sec NoAdmin ${suffix}`);
    const { context, page: user } = await loginAsUser(browser, email, password);
    try {
      await expectAllowed(user, '/dashboard');
      await expectAllowed(user, '/account');
      for (const path of [
        '/admin',
        '/admin/users',
        '/admin/roles',
        '/admin/permissions',
        '/admin/projects',
        '/admin/appearance',
        '/admin/mailer',
        '/admin/groups',
      ]) {
        await expectForbidden(user, path);
      }
    } finally {
      await context.close();
    }
  });

  test('user without project membership cannot open the demo project (UC-SEC-03)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.sec.nomem.${suffix}@example.invalid`;
    const password = `E2eSecNom1!${suffix}`;

    await createEnabledUser(page, email, password, `Sec NoMem ${suffix}`);
    const uuid = await resolveDemoProjectUuid(page);

    const { context, page: user } = await loginAsUser(browser, email, password);
    try {
      await expectAllowed(user, '/dashboard');
      for (const path of [
        `/projects/${uuid}`,
        `/projects/${uuid}/issues`,
        `/projects/${uuid}/analytics`,
        `/projects/${uuid}/performance`,
        `/projects/${uuid}/releases`,
        `/projects/${uuid}/settings/general`,
        `/projects/${uuid}/notifications/new`,
      ]) {
        await expectForbidden(user, path);
      }
    } finally {
      await context.close();
    }
  });

  test('viewer can read issues but is blocked from settings, notifications, and triage (UC-SEC-04)', async ({
    page,
    browser,
  }) => {
    test.setTimeout(150_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.sec.viewer.${suffix}@example.invalid`;
    const password = `E2eSecView1!${suffix}`;

    await createEnabledUser(page, email, password, `Sec Viewer ${suffix}`);
    const uuid = await resolveDemoProjectUuid(page);
    await addProjectMember(page, uuid, email, 'viewer');

    const { context, page: viewer } = await loginAsUser(browser, email, password);
    try {
      await expectAllowed(viewer, `/projects/${uuid}/issues`);
      await expectAllowed(viewer, `/projects/${uuid}/analytics`);
      await expectAllowed(viewer, `/projects/${uuid}/performance`);
      await expectAllowed(viewer, `/projects/${uuid}/releases`);

      // Settings surface requires manage/delete grants — viewers get 403.
      for (const path of [
        `/projects/${uuid}/settings`,
        `/projects/${uuid}/settings/general`,
        `/projects/${uuid}/settings/access`,
        `/projects/${uuid}/settings/alerts`,
        `/projects/${uuid}/settings/data`,
        `/projects/${uuid}/settings/danger`,
        `/projects/${uuid}/notifications/new`,
      ]) {
        await expectForbidden(viewer, path);
      }

      // Help is VIEW-gated (documentation only) — still reachable for viewers.
      await expectAllowed(viewer, `/projects/${uuid}/notifications/help`);

      // Settings tab must not appear for viewers.
      await viewer.goto(`/projects/${uuid}/issues`);
      await expect(viewer.locator('[data-tour="project-settings"], a[href*="/settings"]')).toHaveCount(0);

      const issueUuid = await openFirstIssue(viewer, uuid);
      if (!issueUuid) {
        requireSampleOrSkip(false, 'No issues — run make seed-sample');
        return;
      }
      await expect(viewer.locator('[data-testid="viewer-readonly"]')).toBeVisible({ timeout: 15_000 });

      // Direct triage mutation must be refused even if the form is forged.
      const triage = await viewer.request.post(`/projects/${uuid}/issues/${issueUuid}/assign`, {
        form: { 'issue_assignee[_token]': 'invalid', 'issue_assignee[assignee]': '' },
        failOnStatusCode: false,
      });
      expect(triage.status(), await triage.text()).toBe(403);
    } finally {
      await context.close();
    }
  });

  test('member can triage but cannot open settings or notification management (UC-SEC-05)', async ({
    page,
    browser,
  }) => {
    test.setTimeout(150_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.sec.member.${suffix}@example.invalid`;
    const password = `E2eSecMem1!${suffix}`;

    await createEnabledUser(page, email, password, `Sec Member ${suffix}`);
    const uuid = await resolveDemoProjectUuid(page);
    await addProjectMember(page, uuid, email, 'member');

    const { context, page: member } = await loginAsUser(browser, email, password);
    try {
      await expectAllowed(member, `/projects/${uuid}/issues`);

      const issueUuid = await openFirstIssue(member, uuid);
      if (!issueUuid) {
        requireSampleOrSkip(false, 'No issues — run make seed-sample');
        return;
      }
      // Members may triage — chrome must not be the viewer-only banner.
      await expect(member.locator('[data-testid="viewer-readonly"]')).toHaveCount(0);

      for (const path of [
        `/projects/${uuid}/settings/general`,
        `/projects/${uuid}/settings/access`,
        `/projects/${uuid}/settings/danger`,
        `/projects/${uuid}/notifications/new`,
      ]) {
        await expectForbidden(member, path);
      }

      // Member cannot mint API keys (POST-only route → 403).
      const keys = await member.request.post(`/projects/${uuid}/keys`, {
        form: { 'csrf_token': 'invalid' },
        failOnStatusCode: false,
      });
      expect(keys.status(), await keys.text()).toBe(403);

      // Member cannot delete the project.
      const del = await member.request.post(`/projects/${uuid}/delete`, {
        form: { 'project_delete[confirmation]': 'x', 'project_delete[_token]': 'invalid' },
        failOnStatusCode: false,
      });
      expect(del.status(), await del.text()).toBe(403);
    } finally {
      await context.close();
    }
  });

  test('project admin can open settings but cannot delete the project (UC-SEC-06)', async ({ page, browser }) => {
    test.setTimeout(150_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.sec.padmin.${suffix}@example.invalid`;
    const password = `E2eSecPAdm1!${suffix}`;

    await createEnabledUser(page, email, password, `Sec PAdmin ${suffix}`);
    const uuid = await resolveDemoProjectUuid(page);
    await addProjectMember(page, uuid, email, 'admin');

    const { context, page: admin } = await loginAsUser(browser, email, password);
    try {
      await expectAllowed(admin, `/projects/${uuid}/settings/general`);
      await expectAllowed(admin, `/projects/${uuid}/settings/access`);
      await expectAllowed(admin, `/projects/${uuid}/settings/alerts`);
      await expectAllowed(admin, `/projects/${uuid}/notifications/new`);

      // Danger section is visible for settings.manage, but delete action requires project.delete.
      await expectAllowed(admin, `/projects/${uuid}/settings/danger`);
      const danger = admin.locator('section.panel--danger, section.panel').filter({ hasText: /danger|peligro|zone/i });
      await expect(danger.first()).toBeVisible({ timeout: 15_000 });
      await expect(
        danger.locator('button').filter({ hasText: /delete|eliminar|borrar/i }),
      ).toHaveCount(0);

      const del = await admin.request.post(`/projects/${uuid}/delete`, {
        form: { 'project_delete[confirmation]': 'x', 'project_delete[_token]': 'invalid' },
        failOnStatusCode: false,
      });
      expect(del.status(), await del.text()).toBe(403);
    } finally {
      await context.close();
    }
  });

  test('demoting admin → viewer blocks settings that previously worked (UC-SEC-07)', async ({ page, browser }) => {
    test.setTimeout(180_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.sec.demote.${suffix}@example.invalid`;
    const password = `E2eSecDem1!${suffix}`;

    await createEnabledUser(page, email, password, `Sec Demote ${suffix}`);
    const uuid = await resolveDemoProjectUuid(page);
    await addProjectMember(page, uuid, email, 'admin');

    const { context, page: actor } = await loginAsUser(browser, email, password);
    try {
      await expectAllowed(actor, `/projects/${uuid}/settings/general`);
      await expectAllowed(actor, `/projects/${uuid}/notifications/new`);
    } finally {
      await context.close();
    }

    await changeProjectMemberRole(page, uuid, email, 'viewer');

    const again = await loginAsUser(browser, email, password);
    try {
      await expectAllowed(again.page, `/projects/${uuid}/issues`);
      await expectForbidden(again.page, `/projects/${uuid}/settings/general`);
      await expectForbidden(again.page, `/projects/${uuid}/notifications/new`);
    } finally {
      await again.context.close();
    }
  });

  test('deactivated membership revokes project access (UC-SEC-08)', async ({ page, browser }) => {
    test.setTimeout(150_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.sec.inactive.${suffix}@example.invalid`;
    const password = `E2eSecIna1!${suffix}`;

    await createEnabledUser(page, email, password, `Sec Inactive ${suffix}`);
    const uuid = await resolveDemoProjectUuid(page);
    await addProjectMember(page, uuid, email, 'member');

    const first = await loginAsUser(browser, email, password);
    try {
      await expectAllowed(first.page, `/projects/${uuid}/issues`);
    } finally {
      await first.context.close();
    }

    await deactivateProjectMember(page, uuid, email);

    const second = await loginAsUser(browser, email, password);
    try {
      await expectForbidden(second.page, `/projects/${uuid}/issues`);
      await expectForbidden(second.page, `/projects/${uuid}/settings/general`);
    } finally {
      await second.context.close();
    }
  });
});
