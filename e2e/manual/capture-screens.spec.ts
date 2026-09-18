/**
 * Documentation screenshot crawl for docs/manual/.
 *
 * Fixed viewport 1440×900 only (no fullPage stretch — preserves aside chrome).
 * Default: English + day theme. Day/night and locale UI are demonstrated once
 * on a public route and once on a private route (see prefs-* shots).
 *
 * Run: make docs-manual-screenshots
 */
import { expect, test, type Browser, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  captureManualScreenshot,
  clearMaintenanceScheduleForManual,
  dismissCookieConsent,
  ensureEnglishUi,
  filterManualAdminGroups,
  filterManualDashboardProjects,
  gotoStable,
  MANUAL_VIEWPORT,
  openFirstIssue,
  prepareProductionScreenshot,
  resolveDemoProjectUuid,
  setManualTheme,
} from '../support/helpers';
import {
  inspectManualAuthShot,
  inspectManualPage,
} from '../support/manual-page-gate';

const rootDir = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const outDir = path.join(rootDir, 'docs', 'manual', 'images');
/** Collected during this run — fail the suite when MANUAL_REQUIRE_HEALTHY=1 (default). */
const manualErrorShots: string[] = [];

function shotWanted(name: string): boolean {
  const only = (process.env.MANUAL_ONLY ?? '')
    .split(',')
    .map((item) => item.trim())
    .filter((item) => item !== '');

  return only.length === 0 || only.includes(name);
}

type Shot = { name: string; path: string; maxParts?: number };

const guestShots: Shot[] = [
  { name: 'auth-login', path: '/login' },
  { name: 'auth-register', path: '/register' },
  { name: 'auth-reset-password', path: '/reset-password' },
  { name: 'auth-magic-link', path: '/login/magic' },
  { name: 'auth-qr', path: '/login/qr' },
  { name: 'legal-notice', path: '/en/legal/notice', maxParts: 2 },
  { name: 'legal-privacy', path: '/en/legal/privacy', maxParts: 2 },
  { name: 'legal-terms', path: '/en/legal/terms', maxParts: 2 },
  { name: 'legal-cookies', path: '/en/legal/cookies', maxParts: 2 },
];

const errorShots: Shot[] = [
  { name: 'error-400', path: '/_error/400' },
  { name: 'error-401', path: '/_error/401' },
  { name: 'error-403', path: '/_error/403' },
  { name: 'error-404', path: '/_error/404' },
  { name: 'error-408', path: '/_error/408' },
  { name: 'error-429', path: '/_error/429' },
  { name: 'error-500', path: '/_error/500' },
  { name: 'error-502', path: '/_error/502' },
  { name: 'error-503', path: '/_error/503' },
  { name: 'error-maintenance', path: '/_maintenance_preview' },
];

const dashboardShots: Shot[] = [
  { name: 'dashboard', path: '/dashboard' },
  { name: 'dashboard-activity', path: '/dashboard/activity' },
  { name: 'dashboard-alerts', path: '/dashboard/alerts' },
  { name: 'dashboard-assignments', path: '/dashboard/assignments' },
  { name: 'dashboard-mentions', path: '/dashboard/mentions' },
  { name: 'dashboard-summary', path: '/dashboard/summary' },
  { name: 'dashboard-new-in-release', path: '/dashboard/new-in-release' },
  { name: 'projects-new', path: '/projects/new', maxParts: 2 },
];

const accountShots: Shot[] = [
  { name: 'account', path: '/account' },
  { name: 'account-profile', path: '/account/profile', maxParts: 2 },
  { name: 'account-projects', path: '/account/projects' },
  { name: 'account-groups', path: '/account/groups' },
  { name: 'account-security', path: '/account/security', maxParts: 2 },
  { name: 'account-security-activity', path: '/account/security/activity' },
  { name: 'account-security-devices', path: '/account/security/devices' },
  { name: 'account-security-history', path: '/account/security/history' },
  { name: 'account-display', path: '/account/display', maxParts: 2 },
  { name: 'account-display-panels', path: '/account/display/panels', maxParts: 2 },
  { name: 'account-display-tours', path: '/account/display/tours' },
  { name: 'account-display-notifications', path: '/account/display/notifications', maxParts: 2 },
  { name: 'account-privacy', path: '/account/privacy' },
];

const adminShots: Shot[] = [
  { name: 'admin', path: '/admin' },
  { name: 'admin-users', path: '/admin/users' },
  { name: 'admin-users-new', path: '/admin/users/new', maxParts: 2 },
  { name: 'admin-groups', path: '/admin/groups?q=Beacon' },
  { name: 'admin-groups-new', path: '/admin/groups?new=1' },
  { name: 'admin-projects', path: '/admin/projects' },
  { name: 'admin-projects-new', path: '/admin/projects?new=1' },
  { name: 'admin-roles', path: '/admin/roles' },
  { name: 'admin-roles-new', path: '/admin/roles/new' },
  { name: 'admin-permissions', path: '/admin/permissions' },
  { name: 'admin-permissions-new', path: '/admin/permissions/new' },
  { name: 'admin-ops', path: '/admin/ops' },
  { name: 'admin-mailer', path: '/admin/mailer', maxParts: 2 },
  { name: 'admin-appearance', path: '/admin/appearance', maxParts: 2 },
  { name: 'admin-instance-config', path: '/admin/instance-config', maxParts: 2 },
  { name: 'admin-ops-defaults', path: '/admin/ops-defaults', maxParts: 2 },
  { name: 'admin-mercure', path: '/admin/mercure', maxParts: 2 },
  { name: 'admin-social-login', path: '/admin/social-login' },
  { name: 'admin-social-login-new', path: '/admin/social-login/new' },
  { name: 'admin-http-log', path: '/admin/http-log' },
  { name: 'admin-menus', path: '/admin/menus/' },
  { name: 'admin-routing', path: '/admin/_routing/' },
  { name: 'admin-routing-new', path: '/admin/_routing/new' },
  { name: 'admin-breadcrumbs', path: '/breadcrumb-kit-admin/collections' },
  { name: 'admin-breadcrumbs-new', path: '/breadcrumb-kit-admin/collections/new' },
  { name: 'admin-cookie-consent', path: '/admin/cookie-consent', maxParts: 2 },
  { name: 'admin-legal', path: '/admin/legal' },
  { name: 'admin-legal-edit', path: '/admin/legal/notice/en' },
  { name: 'admin-maintenance', path: '/admin/maintenance' },
  { name: 'admin-site-backup', path: '/_site_backup', maxParts: 2 },
  { name: 'admin-site-backup-history', path: '/_site_backup/history' },
];



function noteManualGate(shotName: string, gate: { ok: boolean; kind?: string; reason?: string }): boolean {
  if (!gate.ok) {
    test.info().annotations.push({
      type: 'skip-shot',
      description: `${shotName} skipped — ${gate.reason ?? 'page gate'}`,
    });
    // Drop stale PNG so manuals never keep a previous wrong-page capture.
    try {
      fs.unlinkSync(path.join(outDir, `${shotName}.png`));
    } catch {
      /* missing is fine */
    }
    return false;
  }
  if (gate.kind === 'error-page') {
    test.info().annotations.push({
      type: 'error-shot',
      description: `${shotName} captured application error — ${gate.reason ?? '500'}`,
    });
    manualErrorShots.push(shotName);
  }
  return true;
}

async function openLocaleMenu(page: Page): Promise<boolean> {
  const switcher = page.locator('.locale-switcher').first();
  if (!(await switcher.isVisible().catch(() => false))) {
    return false;
  }
  const details = switcher.locator('details.locale-switcher__details').first();
  const summary = switcher.locator('summary.locale-switcher__summary').first();
  if ((await details.count()) > 0 && (await details.getAttribute('open')) === null) {
    await summary.click({ force: true }).catch(() => undefined);
    await page.waitForTimeout(150);
  }
  return (await details.getAttribute('open')) !== null || (await switcher.locator('.locale-switcher__menu').isVisible().catch(() => false));
}

async function lockGuestEnglish(page: Page): Promise<void> {
  await gotoStable(page, '/login');
  await dismissCookieConsent(page);
  await ensureEnglishUi(page);
  const enOption = page.locator('form[action*="/locale/en"] button.locale-switcher__option').first();
  if (await enOption.isVisible().catch(() => false)) {
    await enOption.click({ force: true }).catch(() => undefined);
    await page.waitForLoadState('domcontentloaded').catch(() => undefined);
  }
  await expect(page.locator('html')).toHaveAttribute('lang', /^en\b/i, {
    timeout: 8_000,
  });
}

async function shoot(page: Page, shot: Shot): Promise<void> {
  if (!shotWanted(shot.name)) {
    return;
  }
  await gotoStable(page, shot.path);

  const gate = shot.name.startsWith('auth-')
    ? await inspectManualAuthShot(page, shot.path)
    : await inspectManualPage(page, { expectedPath: shot.path });
  if (!noteManualGate(shot.name, gate)) {
    await page.goto('about:blank').catch(() => undefined);
    return;
  }

  if (shot.name.startsWith('error-')) {
    await expect(page.locator('html')).toHaveAttribute('lang', /^en\b/i);
  }

  if (shot.name === 'dashboard') {
    await filterManualDashboardProjects(page);
  }

  if (shot.name === 'admin-groups') {
    await filterManualAdminGroups(page);
  }

  await captureManualScreenshot(page, outDir, shot.name, {
    theme: 'light',
    maxParts: shot.maxParts ?? 1,
    beforeShot: shot.name === 'admin-legal-edit' ? waitForLegalEditor : undefined,
  });
  await page.goto('about:blank').catch(() => undefined);
}

async function waitForLegalEditor(page: Page): Promise<void> {
  await page.locator('.ck-editor').first().waitFor({ state: 'visible', timeout: 20_000 });
}

async function shootOptional(page: Page, shot: Shot): Promise<void> {
  try {
    await shoot(page, shot);
  } catch (error) {
    const reason = error instanceof Error ? error.message : 'preview unavailable';
    test.info().annotations.push({
      type: 'skip-shot',
      description: `${shot.name} skipped — ${reason}`,
    });
    try {
      fs.unlinkSync(path.join(outDir, `${shot.name}.png`));
    } catch {
      /* missing is fine */
    }
    await page.goto('about:blank').catch(() => undefined);
  }
}


/**
 * Once: public + private day/night, and how to open the language menu.
 * Remaining inventory stays English + day.
 */
async function shootThemeAndLocaleDemo(page: Page, kind: 'public' | 'private'): Promise<void> {
  const pathName = kind === 'public' ? '/login' : '/dashboard';
  const prefix = kind === 'public' ? 'prefs-public' : 'prefs-private';

  await gotoStable(page, pathName);
  await prepareProductionScreenshot(page);
  await ensureEnglishUi(page);
  const themeGate = await inspectManualPage(page, { expectedPath: pathName });
  if (!noteManualGate(`${prefix}-theme`, themeGate)) {
    await page.goto('about:blank').catch(() => undefined);
    return;
  }


  if (kind === 'private') {
    await filterManualDashboardProjects(page);
  }

  await captureManualScreenshot(page, outDir, `${prefix}-theme`, { theme: 'light', maxParts: 1 });
  await captureManualScreenshot(page, outDir, `${prefix}-theme`, { theme: 'dark', maxParts: 1 });

  // Back to day for locale demo (matches the rest of the manual).
  await setManualTheme(page, 'light');
  if (kind === 'private') {
    await filterManualDashboardProjects(page);
  } else {
    await gotoStable(page, pathName);
    await prepareProductionScreenshot(page);
    await ensureEnglishUi(page);
  }
  await setManualTheme(page, 'light');

  if (await openLocaleMenu(page)) {
    await prepareProductionScreenshot(page);
    await setManualTheme(page, 'light');
    // Keep menu open after prepare — re-open if closed.
    await openLocaleMenu(page);
    await page.setViewportSize(MANUAL_VIEWPORT);
    fs.mkdirSync(outDir, { recursive: true });
    await page.screenshot({
      path: path.join(outDir, `${prefix}-locale-menu.png`),
      fullPage: false,
      animations: 'disabled',
      caret: 'hide',
    });
  }

  // Restore English for the rest of the suite.
  await ensureEnglishUi(page);
  await page.goto('about:blank').catch(() => undefined);
}

async function lockEnglishForSession(page: Page): Promise<void> {
  await gotoStable(page, '/dashboard');
  await captureManualScreenshot(page, outDir, '_warmup-en', { theme: 'light', maxParts: 1 });
  try {
    fs.unlinkSync(path.join(outDir, '_warmup-en.png'));
  } catch {
    /* ignore */
  }
}

async function withGuestPage(browser: Browser, run: (page: Page) => Promise<void>): Promise<void> {
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    locale: 'en-US',
    viewport: { ...MANUAL_VIEWPORT },
    colorScheme: 'light',
    storageState: { cookies: [], origins: [] },
  });
  const page = await context.newPage();
  try {
    await run(page);
  } finally {
    await context.close();
  }
}

test.describe.configure({ mode: 'serial' });

test.describe('Product UI manual screenshots', () => {
  test('theme and language demos (public + private)', async ({ browser, page }) => {
    await withGuestPage(browser, async (guest) => {
      await shootThemeAndLocaleDemo(guest, 'public');
    });
    await page.setViewportSize(MANUAL_VIEWPORT);
    await lockEnglishForSession(page);
    await shootThemeAndLocaleDemo(page, 'private');
  });

  test('guest and legal screens', async ({ browser }) => {
    await withGuestPage(browser, async (page) => {
      await lockGuestEnglish(page);
      for (const shot of guestShots) {
        await shoot(page, shot);
      }
    });
  });

  test('public error screens', async ({ browser, page }) => {
    // E2E suites often leave a 2099 schedule → absurd countdown on preview.
    await page.setViewportSize(MANUAL_VIEWPORT);
    await clearMaintenanceScheduleForManual(page);

    await withGuestPage(browser, async (guest) => {
      // Error chrome has no locale switcher — lock English on /login first (session `_locale`).
      await lockGuestEnglish(guest);
      for (const shot of errorShots) {
        if (shot.name === 'error-maintenance') {
          await shootOptional(guest, shot);
          continue;
        }
        await shoot(guest, shot);
      }
    });
  });

  test('dashboard screens', async ({ page }) => {
    await page.setViewportSize(MANUAL_VIEWPORT);
    await lockEnglishForSession(page);
    for (const shot of dashboardShots) {
      await shoot(page, shot);
    }
  });

  test('account screens', async ({ page }) => {
    await page.setViewportSize(MANUAL_VIEWPORT);
    await lockEnglishForSession(page);
    for (const shot of accountShots) {
      await shoot(page, shot);
    }
  });

  test('project and issue screens', async ({ page }) => {
    await page.setViewportSize(MANUAL_VIEWPORT);
    await lockEnglishForSession(page);
    const projectUuid = await resolveDemoProjectUuid(page);
    const projectShots: Shot[] = [
      { name: 'project-issues', path: `/projects/${projectUuid}/issues` },
      { name: 'project-analytics', path: `/projects/${projectUuid}/analytics` },
      { name: 'project-performance', path: `/projects/${projectUuid}/performance` },
      { name: 'project-releases', path: `/projects/${projectUuid}/releases` },
      { name: 'project-settings-general', path: `/projects/${projectUuid}/settings/general`, maxParts: 2 },
      { name: 'project-settings-access', path: `/projects/${projectUuid}/settings/access`, maxParts: 2 },
      { name: 'project-settings-alerts', path: `/projects/${projectUuid}/settings/alerts`, maxParts: 2 },
      { name: 'project-settings-data', path: `/projects/${projectUuid}/settings/data`, maxParts: 2 },
      { name: 'project-settings-danger', path: `/projects/${projectUuid}/settings/danger` },
      { name: 'project-notifications-new', path: `/projects/${projectUuid}/notifications/new`, maxParts: 2 },
      { name: 'project-notifications-help', path: `/projects/${projectUuid}/notifications/help`, maxParts: 2 },
      { name: 'project-threshold-rules-new', path: `/projects/${projectUuid}/settings/alerts?new_threshold=1` },
    ];
    for (const shot of projectShots) {
      await shoot(page, shot);
    }

    const issueUuid = await openFirstIssue(page, projectUuid);
    if (!issueUuid) {
      test.info().annotations.push({ type: 'skip-shot', description: 'no sample issue for detail tabs' });
      return;
    }
    const issueShots: Shot[] = [
      { name: 'issue-detail', path: `/projects/${projectUuid}/issues/${issueUuid}`, maxParts: 2 },
      { name: 'issue-similar', path: `/projects/${projectUuid}/issues/${issueUuid}/similar` },
      { name: 'issue-history', path: `/projects/${projectUuid}/issues/${issueUuid}/history` },
    ];
    for (const shot of issueShots) {
      await shoot(page, shot);
    }
  });

  test('admin identity screens', async ({ page }) => {
    await page.setViewportSize(MANUAL_VIEWPORT);
    await lockEnglishForSession(page);
    await filterManualAdminGroups(page);
    const identity = adminShots.filter((s) =>
      /admin$|admin-users|admin-groups|admin-projects|admin-roles|admin-permissions/.test(s.name),
    );
    for (const shot of identity) {
      await shoot(page, shot);
    }
    await gotoStable(page, '/admin/users');
    const userActivity = page.locator('a[href*="/admin/users/"][href*="/activity"]').first();
    if ((await userActivity.count()) > 0) {
      const href = await userActivity.getAttribute('href');
      if (href) {
        await shoot(page, { name: 'admin-user-activity', path: href, maxParts: 2 });
      }
    }
  });

  test('admin ops and kit screens', async ({ page }) => {
    await page.setViewportSize(MANUAL_VIEWPORT);
    await lockEnglishForSession(page);
    const ops = adminShots.filter(
      (s) => !/admin$|admin-users|admin-groups|admin-projects|admin-roles|admin-permissions/.test(s.name),
    );
    for (const shot of ops) {
      await shoot(page, shot);
    }
  });

  test('admin legal editor screens', async ({ page }) => {
    await page.setViewportSize(MANUAL_VIEWPORT);
    await lockEnglishForSession(page);
    for (const shot of adminShots.filter((shot) => shot.name.startsWith('admin-legal'))) {
      await shoot(page, shot);
    }
  });

  test('manual screenshots must not be application errors', async () => {
    const requireHealthy = process.env.MANUAL_REQUIRE_HEALTHY !== '0';
    if (!requireHealthy) {
      return;
    }
    expect(manualErrorShots, `error-page shots (fix UI then re-run): ${manualErrorShots.join(', ')}`).toEqual([]);
  });

});
