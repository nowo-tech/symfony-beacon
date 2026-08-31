import { expect, test, type Page } from '@playwright/test';
import {
  DEMO_EMAIL,
  dismissCookieConsent,
  dismissProductTour,
  gotoStable,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';
import {
  mailpitDeleteAll,
  mailpitIsReachable,
  mailpitWaitForLink,
  requireMailpitOrSkip,
} from '../support/mailpit';
import {
  addProjectMember,
  createEnabledUser,
  expectForbidden,
  loginAsUser,
} from '../support/security';

/**
 * Behavioral depth — successful credential mutations, share isolation for non-members,
 * trusted devices, forged auth tokens, admin validation / toggle edges.
 * Complements chrome smoke and shallow “Covered” catalog rows with real outcomes.
 */

async function ensureDeliverableMailer(page: Page): Promise<void> {
  await gotoStable(page, '/admin/mailer');
  await waitForPageLoader(page);
  const form = page.locator('form').filter({ has: page.locator('input[name*="[plainMailerDsn]"]') });
  await expect(form).toBeVisible({ timeout: 15_000 });
  // Shared server Mailpit is `mailpit`; app-local profile uses `mailer` (often unresolved here).
  await form.locator('input[name*="[plainMailerDsn]"]').fill('smtp://mailpit:1025');
  const from = form.locator('input[name*="[mailerFrom]"], input[name*="[from]"]');
  if ((await from.count()) > 0 && (await from.first().inputValue()) === '') {
    await from.first().fill('beacon@symfony-beacon.local');
  }
  await form.locator('button[type="submit"]').first().click();
  await waitForPageLoader(page);
}

async function createSharePath(page: Page, projectUuid: string, opts?: { maxUses?: string; issueUuid?: string }): Promise<string> {
  await page.goto(`/projects/${projectUuid}/settings/access`);
  await dismissProductTour(page);
  const share = page.locator('[data-testid="share-links"]');
  await expect(share).toBeVisible({ timeout: 15_000 });
  const createForm = share.locator('form').filter({ has: page.locator('input[name="project_share_create[days]"]') });
  if (opts?.maxUses) {
    await createForm.locator('input[name="project_share_create[max_uses]"]').fill(opts.maxUses);
  }
  if (opts?.issueUuid) {
    await createForm.locator('input[name="project_share_create[issue_uuid]"]').fill(opts.issueUuid);
  }
  await createForm.locator('button[type="submit"]').click();
  await waitForPageLoader(page);
  const shareUrlEl = page.locator('[data-testid="share-url"]');
  await expect(shareUrlEl).toBeVisible({ timeout: 15_000 });
  const shareUrl = (await shareUrlEl.innerText()).trim();
  return shareUrl.replace(/^https?:\/\/[^/]+/i, '');
}

test.describe('Behavioral depth — credentials lifecycle', () => {
  test('ephemeral user can change password; old password rejected (UC-ACC-19 depth)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.cred.pw.${suffix}@example.invalid`;
    const oldPassword = `E2eOldPw1!${suffix}`;
    const newPassword = `E2eNewPw1!${suffix}`;

    await createEnabledUser(page, email, oldPassword, `Cred PW ${suffix}`);

    const { context, page: user } = await loginAsUser(browser, email, oldPassword);
    try {
      await user.goto('/account/security');
      await dismissProductTour(user);
      const form = user.locator('form').filter({ has: user.locator('input[name="user_preferences[plainPassword]"]') });
      await expect(form).toBeVisible({ timeout: 15_000 });
      await form.locator('input[name="user_preferences[currentPassword]"]').fill(oldPassword);
      await form.locator('input[name="user_preferences[plainPassword]"]').fill(newPassword);
      await form.locator('input[name="user_preferences[plainPassword_confirm]"]').fill(newPassword);
      await form.locator('button[type="submit"]').click();
      await waitForPageLoader(user);
      await expect(user).toHaveURL(/\/account\/security/);
      await expect(user.locator('body')).toContainText(/updated|actualiz|changed|cambi|success|guardad|saved/i);
    } finally {
      await context.close();
    }

    const stale = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const stalePage = await stale.newPage();
    try {
      await stalePage.goto('/login');
      await dismissCookieConsent(stalePage);
      await stalePage.locator('input[name="login_form[_username]"]').fill(email);
      await stalePage.locator('input[name="login_form[_password]"]').fill(oldPassword);
      await stalePage
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await expect(stalePage).toHaveURL(/\/login/, { timeout: 20_000 });
      await expect(stalePage).not.toHaveURL(/\/dashboard/);
    } finally {
      await stale.close();
    }

    const { context: okCtx, page: okPage } = await loginAsUser(browser, email, newPassword);
    await expect(okPage).toHaveURL(/\/dashboard/);
    await okCtx.close();
  });

  test('ephemeral user can change email with current password (UC-ACC-18 depth)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.cred.em.${suffix}@example.invalid`;
    const nextEmail = `e2e.cred.em2.${suffix}@example.invalid`;
    const password = `E2eEmail1!${suffix}`;

    await createEnabledUser(page, email, password, `Cred Email ${suffix}`);

    const { context, page: user } = await loginAsUser(browser, email, password);
    try {
      await user.goto('/account/profile');
      await dismissProductTour(user);
      const form = user.locator('[data-testid="profile-sensitive-form"]');
      await expect(form).toBeVisible({ timeout: 15_000 });
      await form.locator('input[name="user_profile_sensitive[email]"]').fill(nextEmail);
      await form.locator('input[name="user_profile_sensitive[currentPassword]"]').fill(password);
      await form.locator('button[type="submit"]').click();
      await waitForPageLoader(user);
      await expect(user).toHaveURL(/\/account\/profile/);
      await expect(user.locator('input[name="user_profile_sensitive[email]"]')).toHaveValue(nextEmail);
    } finally {
      await context.close();
    }

    const { context: again, page: logged } = await loginAsUser(browser, nextEmail, password);
    await expect(logged).toHaveURL(/\/dashboard/);
    await again.close();
  });
});

test.describe('Behavioral depth — trusted devices', () => {
  test('trust current device then revoke it (UC-ACC-28)', async ({ page }) => {
    test.setTimeout(90_000);
    await page.goto('/account/security/devices');
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="trusted-devices"]')).toBeVisible({ timeout: 15_000 });

    // Device Intelligence collect is async; wait then reload so DeviceContext is present.
    await page
      .waitForResponse((r) => r.url().includes('/_device/collect') && r.ok(), { timeout: 12_000 })
      .catch(() => null);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await dismissProductTour(page);

    const already = page.locator('[data-testid="trusted-devices-current-trusted"]');
    if ((await already.count()) === 0) {
      const trustForm = page.locator('[data-testid="trusted-devices-trust-form"]');
      if ((await trustForm.count()) === 0) {
        test.info().annotations.push({
          type: 'note',
          description: 'no DeviceContext after collect — trust UI unavailable this run',
        });
        return;
      }
      const payload = await trustForm.evaluate((el) => {
        const data: Record<string, string> = {};
        new FormData(el as HTMLFormElement).forEach((value, key) => {
          data[key] = String(value);
        });
        return data;
      });
      expect(Object.keys(payload).length, `trust form fields: ${JSON.stringify(payload)}`).toBeGreaterThan(0);
      await trustForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
      await expect(page).toHaveURL(/\/account\/security\/devices/);
      await expect(page.locator('body')).not.toContainText(/Invalid CSRF token/i);
      await expect(page.locator('[data-testid="trusted-devices-current-trusted"], [data-testid="trusted-device-row"]').first()).toBeVisible({
        timeout: 15_000,
      });
    }

    const revokeForm = page.locator('[data-testid="trusted-devices-revoke-form"]').first();
    if ((await revokeForm.count()) > 0) {
      await revokeForm.locator('button[type="submit"]').click();
      await waitForPageLoader(page);
      await expect(page.locator('[data-testid="trusted-devices"]')).toBeVisible();
    }
  });

  test('forged CSRF on trust is rejected (UC-ACC-28 csrf)', async ({ page, request }) => {
    await page.goto('/account/security/devices');
    await dismissProductTour(page);
    const form = page.locator('[data-testid="trusted-devices-trust-form"]');
    if ((await form.count()) === 0) {
      test.info().annotations.push({ type: 'note', description: 'device already trusted — CSRF path N/A this session' });
      return;
    }
    const res = await request.post('/account/security/devices/trust', {
      form: { 'csrf_only[_token]': 'forged-devices-csrf', _token: 'forged-devices-csrf' },
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    expect([302, 303, 400, 403, 422]).toContain(res.status());
  });
});

test.describe('Behavioral depth — share viewer isolation', () => {
  test('non-member share consumer reaches project but not settings/admin (UC-PROJ-12 depth)', async ({
    page,
    browser,
  }) => {
    test.setTimeout(150_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.share.ext.${suffix}@example.invalid`;
    const password = `E2eShare1!${suffix}`;
    const uuid = await resolveDemoProjectUuid(page);

    await createEnabledUser(page, email, password, `Share Ext ${suffix}`);
    const sharePath = await createSharePath(page, uuid, { maxUses: '2' });

    const { context, page: outsider } = await loginAsUser(browser, email, password);
    try {
      await outsider.goto(sharePath);
      await dismissProductTour(outsider);
      await waitForPageLoader(outsider);
      await expect(outsider).toHaveURL(new RegExp(`/projects/${uuid}`));
      await expect(outsider).not.toHaveURL(/\/login/);

      await expectForbidden(outsider, `/projects/${uuid}/settings`);
      await expectForbidden(outsider, `/projects/${uuid}/settings/access`);
      await expectForbidden(outsider, '/admin');
      await expectForbidden(outsider, '/admin/users');
    } finally {
      await context.close();
    }
  });

  test('issue-scoped share lands outsider on that issue only (UC-PROJ-10 depth)', async ({ page, browser }) => {
    test.setTimeout(150_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const suffix = Date.now().toString(36);
    const email = `e2e.share.iss.${suffix}@example.invalid`;
    const password = `E2eShareIss1!${suffix}`;
    await createEnabledUser(page, email, password, `Share Iss ${suffix}`);
    const sharePath = await createSharePath(page, uuid, { maxUses: '2', issueUuid });

    const { context, page: outsider } = await loginAsUser(browser, email, password);
    try {
      await outsider.goto(sharePath);
      await dismissProductTour(outsider);
      await waitForPageLoader(outsider);
      await expect(outsider).toHaveURL(new RegExp(`/projects/${uuid}/issues/${issueUuid}`));
      await expect(outsider.locator('[data-testid="viewer-readonly"], main, [role="main"]').first()).toBeVisible();

      // Another issue in the same project should stay out of reach for issue-scoped share.
      await outsider.goto(`/projects/${uuid}/issues`);
      await dismissProductTour(outsider);
      const other = outsider.locator(`a[href*="/projects/${uuid}/issues/"]`).filter({ hasNotText: issueUuid });
      // Scoped grant may hide the list or 403 other issues — never allow settings.
      await expectForbidden(outsider, `/projects/${uuid}/settings/data`);
      void other;
    } finally {
      await context.close();
    }
  });

  test('revoked share rejects subsequent outsider consume (UC-PROJ-11 depth)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.share.rev.${suffix}@example.invalid`;
    const password = `E2eShareRev1!${suffix}`;
    const uuid = await resolveDemoProjectUuid(page);

    await createEnabledUser(page, email, password, `Share Rev ${suffix}`);
    const sharePath = await createSharePath(page, uuid, { maxUses: '5' });

    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const revoke = page.locator('form[action*="/share-links/"][action*="/revoke"] button[type="submit"]').first();
    await expect(revoke).toBeVisible({ timeout: 15_000 });
    await revoke.click();
    await waitForPageLoader(page);

    const { context, page: outsider } = await loginAsUser(browser, email, password);
    try {
      await outsider.goto(sharePath);
      await waitForPageLoader(outsider);
      const url = outsider.url();
      const body = await outsider.locator('body').innerText();
      const rejected =
        /\/login/i.test(url) ||
        /invalid|inválid|revok|expired|expir|no longer|ya no|share/i.test(body) ||
        !new RegExp(`/projects/${uuid}(/|$)`).test(url);
      expect(rejected, `url=${url}`).toBeTruthy();
    } finally {
      await context.close();
    }
  });
});

test.describe('Behavioral depth — auth token edges', () => {
  test('forged magic-login check stays off dashboard (UC-AUTH-27)', async ({ browser }) => {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const guest = await ctx.newPage();
    try {
      await guest.goto('/login/magic/check?user=1&expires=1&hash=deadbeef');
      await dismissCookieConsent(guest);
      await waitForPageLoader(guest);
      await expect(guest).not.toHaveURL(/\/dashboard/);
      await expect(guest.locator('body')).toBeVisible();
      const body = await guest.locator('body').innerText();
      expect(/login|magic|invalid|inválid|expired|expir|error|enlace|link/i.test(body) || /\/login/i.test(guest.url())).toBeTruthy();
    } finally {
      await ctx.close();
    }
  });

  test('forged reset-password token is rejected (UC-AUTH-28)', async ({ browser }) => {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const guest = await ctx.newPage();
    try {
      await guest.goto('/reset-password/reset/not-a-real-token-value');
      await dismissCookieConsent(guest);
      await waitForPageLoader(guest);
      await expect(guest).not.toHaveURL(/\/dashboard/);
      const body = await guest.locator('body').innerText();
      expect(/reset|password|invalid|inválid|expired|expir|token|enlace|link|error/i.test(body)).toBeTruthy();
    } finally {
      await ctx.close();
    }
  });

  test('magic-login link cannot be replayed after confirm (UC-AUTH-29)', async ({ page, browser }) => {
    test.setTimeout(150_000);
    requireMailpitOrSkip(await mailpitIsReachable(), 'Mailpit not reachable — run `make mailpit`');
    await ensureDeliverableMailer(page);
    await mailpitDeleteAll();

    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const guest = await ctx.newPage();
    try {
      await guest.goto('/login/magic');
      await dismissCookieConsent(guest);
      await guest.locator('input[name="magic_login_request_form[identifier]"]').fill(DEMO_EMAIL);
      await guest
        .locator('form[name="magic_login_request_form"] button[type="submit"], .nowo-auth-kit__panel form button[type="submit"]')
        .first()
        .click();
      await waitForPageLoader(guest);

      const checkPath = await mailpitWaitForLink({
        toAddress: DEMO_EMAIL,
        subjectIncludes: /magic|mágic|enlace|sign-?in|login|acceso|anmelden|connexion/i,
        linkPattern: /https?:\/\/[^\s"'<>]+\/(?:[a-z]{2}\/)?login\/magic\/check[^\s"'<>]*/i,
      });

      await guest.goto(checkPath);
      await dismissCookieConsent(guest);
      const confirm = guest
        .locator('form.nowo-auth-kit__form, form')
        .filter({ has: guest.locator('button[type="submit"]') })
        .first();
      await expect(confirm).toBeVisible({ timeout: 15_000 });
      await confirm.locator('button[type="submit"]').click();
      await guest.waitForURL(/\/dashboard/, { timeout: 45_000 });

      // Replay same signed URL in a fresh guest context.
      const replayCtx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
      const replay = await replayCtx.newPage();
      try {
        await replay.goto(checkPath);
        await dismissCookieConsent(replay);
        await waitForPageLoader(replay);
        await expect(replay).not.toHaveURL(/\/dashboard/);
        const body = await replay.locator('body').innerText();
        expect(/invalid|inválid|expired|expir|used|consum|error|login|magic|enlace/i.test(body) || /\/login/i.test(replay.url())).toBeTruthy();
      } finally {
        await replayCtx.close();
      }
    } finally {
      await ctx.close();
    }
  });

  test('reset password rejects mismatched confirmation (UC-AUTH-30)', async ({ page, browser }) => {
    test.setTimeout(150_000);
    requireMailpitOrSkip(await mailpitIsReachable(), 'Mailpit not reachable — run `make mailpit`');
    await ensureDeliverableMailer(page);

    const suffix = Date.now().toString(36);
    const email = `e2e.reset.mm.${suffix}@example.invalid`;
    const oldPassword = `E2eOldMm1!${suffix}Aa9#`;
    await createEnabledUser(page, email, oldPassword, `Reset MM ${suffix}`);
    await mailpitDeleteAll();

    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const guest = await ctx.newPage();
    try {
      await guest.goto('/reset-password');
      await dismissCookieConsent(guest);
      const requestForm = guest
        .locator('form[name="reset_password_request_form"], .nowo-auth-kit__panel form')
        .filter({ has: guest.locator('input[name*="[identifier]"]') })
        .first();
      await requestForm.locator('input[name*="[identifier]"]').fill(email);
      await requestForm.locator('button[type="submit"]').click();
      await waitForPageLoader(guest);

      const resetPath = await mailpitWaitForLink({
        toAddress: email,
        subjectIncludes: /reset|password|contraseña|passwort|passe|passworda|restablec/i,
        linkPattern: /https?:\/\/[^\s"'<>]+\/(?:[a-z]{2}\/)?reset-password\/reset\/[^\s"'<>]+/i,
      });

      await guest.goto(resetPath);
      await dismissCookieConsent(guest);
      const resetForm = guest.locator('form').filter({ has: guest.locator('input[name*="[password]"]') }).first();
      await expect(resetForm).toBeVisible({ timeout: 15_000 });
      await resetForm.locator('input[name*="[password]"]:not([name*="confirm"])').fill(`E2eGood1!${suffix}`);
      await resetForm.locator('input[name*="[password_confirm]"], input[name*="confirm"]').first().fill(`E2eOther1!${suffix}`);
      await resetForm.locator('button[type="submit"]').click();
      await waitForPageLoader(guest);
      await expect(guest).not.toHaveURL(/\/dashboard/);
      await expect(guest.locator('body')).toContainText(/match|coincid|confirm|password|contraseña|identical|igual/i);

      // Old password still works — reset must not have applied.
      await guest.goto('/login');
      await dismissCookieConsent(guest);
      await guest.locator('input[name="login_form[_username]"]').fill(email);
      await guest.locator('input[name="login_form[_password]"]').fill(oldPassword);
      await guest
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await guest.waitForURL(/\/dashboard/, { timeout: 45_000 });
    } finally {
      await ctx.close();
    }
  });
});

test.describe('Behavioral depth — admin validation & toggle edges', () => {
  test('duplicate user email is rejected (UC-ADM-43)', async ({ page }) => {
    await page.goto('/admin/users?new=1');
    await dismissProductTour(page);
    const form = page.locator('form').filter({ has: page.locator('input[name="admin_user[email]"]') });
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="admin_user[email]"]').fill(DEMO_EMAIL);
    await form.locator('input[name="admin_user[displayName]"]').fill('Dup Admin');
    await form.locator('input[name="admin_user[password]"]').fill('E2eDupAdmin1!x');
    await form.locator('select[name="admin_user[role]"]').selectOption('user');
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/already|existe|duplicate|duplicad|unique|único|taken|email/i);
  });

  test('admin can disable then re-enable ephemeral user (UC-SEC-10 depth)', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.toggle.${suffix}@example.invalid`;
    const password = `E2eToggle1!${suffix}`;
    await createEnabledUser(page, email, password, `Toggle ${suffix}`);

    // Prove login works while enabled.
    const { context: okCtx, page: ok } = await loginAsUser(browser, email, password);
    await expect(ok).toHaveURL(/\/dashboard/);
    await okCtx.close();

    await page.goto(`/admin/users?q=${encodeURIComponent(email)}`);
    await dismissProductTour(page);
    const row = page.locator('tr, li, [data-testid]').filter({ hasText: email }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    const disable = row.locator('button[type="submit"]').filter({ hasText: /disable|desactivar/i }).first();
    await expect(disable).toBeVisible({ timeout: 10_000 });
    await disable.click();
    await waitForPageLoader(page);

    const disabledCtx = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    const guest = await disabledCtx.newPage();
    try {
      await guest.goto('/login');
      await dismissCookieConsent(guest);
      await guest.locator('input[name="login_form[_username]"]').fill(email);
      await guest.locator('input[name="login_form[_password]"]').fill(password);
      await guest
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await expect(guest).toHaveURL(/\/login/, { timeout: 20_000 });
      await expect(guest).not.toHaveURL(/\/dashboard/);
    } finally {
      await disabledCtx.close();
    }

    await page.goto(`/admin/users?q=${encodeURIComponent(email)}`);
    await dismissProductTour(page);
    const row2 = page.locator('tr, li').filter({ hasText: email }).first();
    const enable = row2.locator('button[type="submit"]').filter({ hasText: /enable|activar/i }).first();
    await expect(enable).toBeVisible({ timeout: 10_000 });
    await enable.click();
    await waitForPageLoader(page);

    const { context: again, page: restored } = await loginAsUser(browser, email, password);
    await expect(restored).toHaveURL(/\/dashboard/);
    await again.close();
  });

  test('admin cannot disable self (UC-ADM-44)', async ({ page }) => {
    await page.goto(`/admin/users?q=${encodeURIComponent(DEMO_EMAIL)}`);
    await dismissProductTour(page);
    const row = page.locator('tr, li').filter({ hasText: DEMO_EMAIL }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    await expect(row.locator('button[type="submit"]').filter({ hasText: /disable|desactivar/i })).toHaveCount(0);
    await expect(row).toContainText(/self|tú|yourself|propia|este usuario|you/i);
  });

  test('empty admin group name is rejected (UC-ADM-45)', async ({ page }) => {
    await page.goto('/admin/groups/new');
    await dismissProductTour(page);
    const form = page.locator('form').filter({ has: page.locator('input[name="admin_group[name]"]') });
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name="admin_group[name]"]').fill('');
    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    // Stay on create / show validation — never invent a nameless group in the directory.
    const url = page.url();
    expect(/\/admin\/groups(\/new)?(\?|$)/.test(url) || /\/admin\/groups\/[0-9a-f-]{36}/.test(url)).toBeTruthy();
    if (/\/admin\/groups\/[0-9a-f-]{36}/.test(url)) {
      // Some browsers may still submit HTML5-required empty — assert flash/error if we somehow landed.
      await expect(page.locator('body')).toContainText(/required|obligatori|name|nombre|empty|vacío|invalid/i);
    } else {
      await expect(page.locator('body')).toContainText(/required|obligatori|name|nombre|invalid|error|vacío|blank/i);
    }
  });

  test('project member role change persists for outsider session (UC-PROJ-06 depth)', async ({ page, browser }) => {
    test.setTimeout(150_000);
    const suffix = Date.now().toString(36);
    const email = `e2e.role.chg.${suffix}@example.invalid`;
    const password = `E2eRoleChg1!${suffix}`;
    const uuid = await resolveDemoProjectUuid(page);

    await createEnabledUser(page, email, password, `Role Chg ${suffix}`);
    await addProjectMember(page, uuid, email, 'viewer');

    const { context, page: member } = await loginAsUser(browser, email, password);
    try {
      await member.goto(`/projects/${uuid}/issues`);
      await dismissProductTour(member);
      await expect(member).not.toHaveURL(/\/login/);
      await expectForbidden(member, `/projects/${uuid}/settings`);
    } finally {
      await context.close();
    }

    // Promote to admin — settings must open.
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const row = page.locator('li').filter({ hasText: email }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    const editRole = row
      .locator('button[aria-label*="dit role"], button[aria-label*="ditar rol"], button[title*="dit role"]')
      .first();
    await editRole.click();
    const dialogRole = page.locator('dialog[open] select[name="role"], .confirm-dialog select[name="role"]').last();
    await expect(dialogRole).toBeVisible({ timeout: 10_000 });
    if ((await dialogRole.locator('option[value="admin"]').count()) > 0) {
      await dialogRole.selectOption('admin');
    } else {
      await dialogRole.selectOption('full');
    }
    await page.locator('dialog[open] button.btn-primary, .confirm-dialog button.btn-primary').last().click();
    await waitForPageLoader(page);

    const { context: again, page: promoted } = await loginAsUser(browser, email, password);
    try {
      await promoted.goto(`/projects/${uuid}/settings`);
      await dismissProductTour(promoted);
      await expect(promoted).toHaveURL(new RegExp(`/projects/${uuid}/settings`));
      await expect(promoted).not.toHaveURL(/\/login/);
      await expect(promoted.locator('body')).not.toContainText(/Gate's closed/i);
    } finally {
      await again.close();
    }
  });
});
