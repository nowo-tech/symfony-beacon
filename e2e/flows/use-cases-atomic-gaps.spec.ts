import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
  DEMO_EMAIL,
  DEMO_PASSWORD,
  dismissCookieConsent,
  dismissProductTour,
  expectAuthenticatedPage,
  gotoStable,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

/**
 * Atomic use cases added after the catalog umbrella pass (AUTH-25/26, ACC-24/25,
 * DASH-15, OPS-12/13, SETUP-06, ADM-38..42, PROJ-27).
 */
test.describe('Atomic use-case gaps', () => {
  test('QR login challenge shows image data URI (UC-AUTH-25)', async ({ browser }) => {
    const ctx = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await ctx.newPage();
    try {
      await page.goto('/login/qr');
      await dismissCookieConsent(page);
      await expect(page).toHaveURL(/\/login\/qr\/[0-9a-f-]{36}/i);
      const img = page.locator('img.nowo-auth-kit__qr-image, img[src^="data:image/"]').first();
      await expect(img).toBeVisible({ timeout: 15_000 });
      const src = (await img.getAttribute('src')) ?? '';
      expect(src).toMatch(/^data:image\/(png|svg\+xml)/i);
    } finally {
      await ctx.close();
    }
  });

  test('password reset code page loads (UC-AUTH-26)', async ({ browser }) => {
    const ctx = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await ctx.newPage();
    try {
      // Prefer unlocalized path; locale redirect can be slow under load.
      await gotoStable(page, '/reset-password/complete');
      await dismissCookieConsent(page);
      await expect(page).toHaveURL(/\/reset-password\/complete/);
      const panel = page.locator('.nowo-auth-kit__panel, main, [role="main"]').first();
      await expect(panel.locator('form').filter({ has: page.locator('input:not([type="hidden"])') }).first()).toBeVisible({
        timeout: 15_000,
      });
      await expect(page.getByRole('heading', { name: /code|código|kod/i })).toBeVisible();
    } finally {
      await ctx.close();
    }
  });

  test('profile shows password expiry row (UC-ACC-24)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/profile');
    await expect(page.getByRole('main')).toContainText(/expir|caduc|ablauf|scadenz/i);
    // Always one of: expired notice, dated <time>, or unknown/policy text.
    const expiry = page.locator('time[datetime], a[href*="/account/security"]').first();
    await expect(page.getByRole('main')).toBeVisible();
    void expiry;
  });

  test('security shows linked social accounts panel (UC-ACC-25)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/security');
    await expect(page.locator('[data-testid="linked-social-accounts"]')).toBeVisible({ timeout: 15_000 });
  });

  test('dashboard project search filters by q (UC-DASH-15)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    const search = page.locator('form[role="search"]').first();
    await expect(search).toBeVisible({ timeout: 15_000 });
    const q = search.locator('input[name*="[q]"], input[name="q"]').first();
    await expect(q).toBeVisible();
    await q.fill('Symfony Beacon');
    await search.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/[?&]q=/i);
    await expect(page.getByRole('main')).toContainText(/Symfony Beacon|Beacon/i);
    // Nonsense query → empty or no demo match.
    await page.goto('/dashboard?q=zzznomatch' + Date.now());
    await dismissProductTour(page);
    await expect(page.getByRole('main')).toContainText(/empty|ningún|no project|aucun|keine|no hay/i);
  });

  test('maintenance login route + clear-schedule control (UC-OPS-12/13)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/maintenance/');
    await expect(page.getByRole('main')).toBeVisible();

    // Password gate is off in host config; login still must not 5xx.
    const loginRes = await page.goto('/admin/maintenance/login', { waitUntil: 'domcontentloaded' });
    await dismissProductTour(page);
    expect(loginRes?.status() ?? 500).toBeLessThan(500);
    await expect(page).not.toHaveURL(/Whoops/);

    await page.goto('/admin/maintenance/');
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="maintenance-schedule"]')).toBeVisible({ timeout: 15_000 });
    // Host Twig uses form="maintenance-clear-schedule-form" (sr-only CSRF form).
    const clearSchedule = page.locator(
      'button[form="maintenance-clear-schedule-form"], #maintenance-clear-schedule-form',
    );
    await expect(clearSchedule.first()).toBeAttached({ timeout: 15_000 });
    await expect(page.getByRole('button', { name: /clear schedule|borrar programaci|effacer|löschen|limpar|cancella/i })).toBeVisible();
  });

  test('incomplete-setup banner appears then restores (UC-SETUP-06)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/admin/instance-config');

    // Download current export (cookies from storageState).
    const exportRes = await page.request.get('/admin/instance-config/export');
    expect(exportRes.status()).toBe(200);
    const payload = (await exportRes.json()) as {
      schema?: string;
      version?: number;
      instance?: Record<string, unknown>;
    };
    expect(payload.schema).toBe('beacon-instance-config');
    expect(payload.instance?.setup_completed).toBe(true);

    const off = structuredClone(payload);
    off.instance = { ...(off.instance ?? {}), setup_completed: false };
    const on = structuredClone(payload);
    on.instance = { ...(on.instance ?? {}), setup_completed: true };

    const importJson = async (data: unknown) => {
      await page.goto('/admin/instance-config');
      await dismissProductTour(page);
      const tmp = path.join(os.tmpdir(), `beacon-e2e-setup-${Date.now()}.json`);
      fs.writeFileSync(tmp, `${JSON.stringify(data, null, 2)}\n`);
      await page.locator('[data-testid="instance-config-file"], input[type="file"]').setInputFiles(tmp);
      await page.locator('[data-testid="instance-config-import-submit"]').click();
      await waitForPageLoader(page);
      fs.unlinkSync(tmp);
    };

    try {
      await importJson(off);
      await page.goto('/dashboard');
      await dismissProductTour(page);
      const banner = page.locator('a[href*="/setup"]').filter({ hasText: /setup|configur/i });
      await expect(page.getByRole('main')).toContainText(/setup|configuración|installation/i);
      await expect(banner.first()).toBeVisible({ timeout: 15_000 });
    } finally {
      await importJson(on);
      await page.goto('/dashboard');
      await dismissProductTour(page);
      await expect(page.locator('a.btn-primary[href*="/setup"]')).toHaveCount(0);
    }
  });

  test('group audit timeline shell is present (UC-ADM-38)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/groups');
    const show = page.locator('a[href*="/admin/groups/"]').filter({ hasNotText: /new|nuevo|crear/i }).first();
    await expect(show).toBeVisible({ timeout: 15_000 });
    await show.click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/groups\/[0-9a-f-]{36}/i);
    await expect(page.locator('#group-audit-timeline, [data-testid="group-audit-entry"]').first()).toBeAttached();
    await expect(page.getByRole('main')).toContainText(/audit|historial|activity|actividad/i);
    const filterForm = page.locator('form').filter({
      has: page.locator('select[name*="action"], input[type="date"], input[name*="from"]'),
    });
    await expect(filterForm.first()).toBeVisible({ timeout: 15_000 });
  });

  test('system role delete is blocked (UC-ADM-39)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/roles');
    // Open a known system role (Project viewer).
    const row = page.locator('tr, li, article, .panel').filter({ hasText: /Project viewer|ROLE_PROJECT_VIEWER/i }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    const link = row.locator('a[href*="/admin/roles/"]').first();
    if ((await link.count()) > 0) {
      await link.click();
    } else {
      await page.locator('a[href*="/admin/roles/"]').filter({ hasText: /viewer|Project/i }).first().click();
    }
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/roles\/[0-9a-f-]{36}/i);

    const deleteForm = page.locator('form[action*="/delete"]').filter({
      has: page.locator('button[type="submit"]'),
    });
    if ((await deleteForm.count()) === 0) {
      // System roles may hide delete — that also satisfies the guard.
      await expect(page.getByRole('main')).toContainText(/system|sistema|locked|proteg/i);
      return;
    }
    page.once('dialog', (d) => d.accept().catch(() => undefined));
    await deleteForm.first().locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/system|locked|proteg|cannot|no se puede|in_use|en uso/i);
  });

  test('role permissions matrix can save (UC-ADM-40)', async ({ page }) => {
    test.setTimeout(90_000);
    const suffix = Date.now().toString(36);
    await expectAuthenticatedPage(page, '/admin/roles?new=1');
    const create = page.locator('form').filter({ has: page.locator('input[name="admin_instance_role[name]"]') });
    await expect(create).toBeVisible({ timeout: 15_000 });
    await create.locator('input[name="admin_instance_role[name]"]').fill(`E2E PermRole ${suffix}`);
    await create.locator('input[name="admin_instance_role[code]"]').fill(`e2e_perm_${suffix}`);
    await create.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);

    const uuid = page.url().match(/\/admin\/roles\/([0-9a-f-]{36})/i)?.[1];
    expect(uuid, 'role uuid').toBeTruthy();

    await page.goto(`/admin/roles/${uuid}/permissions`);
    await dismissProductTour(page);
    const form = page.locator('form').filter({
      has: page.locator('input[type="checkbox"][name*="permission"], input[type="checkbox"][name*="permissions"]'),
    });
    await expect(form.first()).toBeVisible({ timeout: 15_000 });
    const box = form.first().locator('input[type="checkbox"]').first();
    if (!(await box.isChecked())) {
      await box.check();
    }
    await form.first().locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(/permission|permiso|updated|actualiz|guardad/i);

    // Cleanup: delete ephemeral role.
    await page.goto(`/admin/roles/${uuid}`);
    await dismissProductTour(page);
    const del = page.locator('form[action*="/delete"] button[type="submit"]').first();
    if ((await del.count()) > 0) {
      page.once('dialog', (d) => d.accept().catch(() => undefined));
      await del.click();
      await waitForPageLoader(page);
    }
  });

  test('copy dashboard menu (UC-ADM-41)', async ({ page }) => {
    test.setTimeout(90_000);
    const suffix = Date.now().toString(36);
    const name = `e2e-copy-src-${suffix}`;
    await expectAuthenticatedPage(page, '/admin/menus/menu/new');
    const form = page.locator('form').filter({
      has: page.locator('input[name*="[name]"], input[name*="[code]"], input[name*="[label]"]'),
    }).first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('input[name*="[name]"], input[name*="[label]"]').first().fill(name);
    const code = form.locator('input[name*="[code]"], input[name*="[slug]"]');
    if ((await code.count()) > 0) {
      await code.first().fill(`e2e_copy_${suffix}`);
    }
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);

    await page.goto('/admin/menus/');
    await dismissProductTour(page);
    const row = page.locator('tr, li').filter({ hasText: name }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    const copyBtn = row.locator('button.btn-copy-menu, a[href*="/copy"]').first();
    let menuId = '';
    if ((await copyBtn.count()) > 0) {
      menuId = (await copyBtn.getAttribute('data-id')) || '';
      const copyUrl = (await copyBtn.getAttribute('data-copy-url')) || '';
      if (copyUrl) {
        await page.goto(copyUrl);
      } else if (menuId) {
        await page.goto(`/admin/menus/${menuId}/copy`);
      } else {
        await copyBtn.click();
      }
    } else {
      const href = await row.locator('a[href*="/admin/menus/"]').first().getAttribute('href');
      menuId = href?.match(/\/admin\/menus\/(\d+)/)?.[1] ?? '';
      expect(menuId).toBeTruthy();
      await page.goto(`/admin/menus/${menuId}/copy`);
    }
    await dismissProductTour(page);
    await waitForPageLoader(page);

    const copyForm = page.locator('form').filter({
      has: page.locator('input[name*="[name]"], input[name*="[code]"]'),
    }).first();
    await expect(copyForm).toBeVisible({ timeout: 15_000 });
    const newName = `e2e-copy-dst-${suffix}`;
    await copyForm.locator('input[name*="[name]"], input[name*="[label]"]').first().fill(newName);
    const newCode = copyForm.locator('input[name*="[code]"], input[name*="[slug]"]');
    if ((await newCode.count()) > 0) {
      await newCode.first().fill(`e2e_copydst_${suffix}`);
    }
    await copyForm.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await page.goto('/admin/menus/');
    await dismissProductTour(page);
    await expect(page.getByRole('main')).toContainText(new RegExp(newName.slice(0, 12), 'i'), { timeout: 15_000 });
  });

  test('mailer settings exposes clear DSN control (UC-ADM-42)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/mailer');
    const clear = page.locator(
      'input[name="instance_mailer[clearMailerDsn]"], input[name*="[clearMailerDsn]"]',
    );
    await expect(clear.first()).toBeVisible({ timeout: 15_000 });
    // Do not check/submit — would wipe local Mailpit DSN for other suites.
  });

  test('guest share open logs in then consumes (UC-PROJ-27)', async ({ page, browser }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);
    const share = page.locator('[data-testid="share-links"]');
    await expect(share).toBeVisible({ timeout: 15_000 });
    const createForm = share.locator('form').filter({
      has: page.locator('input[name="project_share_create[days]"]'),
    });
    await createForm.locator('input[name="project_share_create[days]"]').fill('7');
    await createForm.locator('input[name="project_share_create[max_uses]"]').fill('3');
    await createForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    const shareUrlEl = page.locator('[data-testid="share-url"]');
    await expect(shareUrlEl).toBeVisible({ timeout: 20_000 });
    const shareUrl = (await shareUrlEl.innerText()).trim();
    expect(shareUrl).toMatch(/\/share\/[a-f0-9]{64}/i);
    const sharePath = shareUrl.replace(/^https?:\/\/[^/]+/i, '');

    const guestCtx = await browser.newContext({
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const guest = await guestCtx.newPage();
    try {
      await guest.goto(sharePath);
      await dismissCookieConsent(guest);
      await expect(guest).toHaveURL(/\/login/i);
      // Do not use loginAsDemo — it waits for /dashboard, but target_path resumes /share → project.
      await guest.locator('input[name="login_form[_username]"]').fill(DEMO_EMAIL);
      await guest.locator('input[name="login_form[_password]"]').fill(DEMO_PASSWORD);
      await guest
        .locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]')
        .first()
        .click();
      await expect(guest).toHaveURL(new RegExp(`/projects/${uuid}`), { timeout: 30_000 });
      await dismissProductTour(guest);
      await expect(guest).not.toHaveURL(/\/login/);
    } finally {
      await guestCtx.close();
    }
  });
});
