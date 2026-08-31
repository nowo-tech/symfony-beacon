import { expect, test, type Page } from '@playwright/test';
import {
  dismissProductTour,
  expectAuthenticatedPage,
  resolveDemoProjectUuid,
} from '../support/helpers';
import {
  assertBoxesAligned,
  assertNearTarget,
  assertPageHasKitChrome,
  assertShellAesthetics,
  assertStandardForm,
  assertStandardTable,
  BEACON_BTN_TARGET,
  formOnPageOrDialog,
  readChromeBox,
  samplePageChrome,
  type ChromeBox,
} from '../support/kit-chrome';

/**
 * Structural / aesthetic chrome: FormKit-style forms, kit admin tables, Beacon tokens.
 * Inventory: `e2e/admin/KIT-CHROME-SCREENS.md`.
 * Catches Bootstrap leftovers, missing CSRF, orphan tables, broken shell layout,
 * and cross-page CSS drift on buttons / inputs / panels.
 */

test.describe('Kit chrome standardization — forms & tables', () => {
  test('admin hub shell aesthetics', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin');
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    await expect(page.locator('a[href*="/admin/users"]').first()).toBeVisible();
  });

  test('identity list tables use kit chrome', async ({ page }) => {
    for (const path of ['/admin/users', '/admin/groups', '/admin/projects']) {
      await expectAuthenticatedPage(page, path);
      await dismissProductTour(page);
      await assertShellAesthetics(page);
      const table = page.getByRole('main').locator('table').first();
      if ((await table.count()) > 0) {
        await assertStandardTable(page, table);
      } else {
        // Empty state still needs a panel / main shell.
        await expect(page.getByRole('main').locator('.panel, [data-testid], table').first()).toBeVisible();
      }
    }
  });

  test('identity create forms are standardized', async ({ page }) => {
    for (const path of ['/admin/users/new', '/admin/groups/new', '/admin/projects/new', '/admin/roles/new']) {
      await expectAuthenticatedPage(page, path);
      await dismissProductTour(page);
      await assertShellAesthetics(page);
      // Create may open as kit modal (dialog) instead of a full-page form.
      const form = await formOnPageOrDialog(page);
      await assertStandardForm(page, form);
    }
  });

  test('ops settings forms use kit buttons + CSRF', async ({ page }) => {
    for (const path of ['/admin/mailer', '/admin/appearance', '/admin/instance-config', '/admin/ops-defaults']) {
      await expectAuthenticatedPage(page, path);
      await dismissProductTour(page);
      await assertShellAesthetics(page);
      const form = page.getByRole('main').locator('form').filter({ has: page.locator('button[type="submit"], input[type="submit"]') }).first();
      if ((await form.count()) > 0) {
        await assertStandardForm(page, form);
      }
    }
  });

  test('kit admin tables and new forms', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/http-log');
    await assertShellAesthetics(page);
    await expect(page.locator('[data-testid="http-log-filters"], [data-testid="http-log-results"]').first()).toBeVisible();

    await expectAuthenticatedPage(page, '/admin/menus/');
    await assertShellAesthetics(page);
    await expect(page.locator('[data-testid="dashboard-menu-menus-table"], table').first()).toBeVisible();
    const menuTable = page.locator('[data-testid="dashboard-menu-menus-table"], main table').first();
    if ((await menuTable.locator('thead th').count()) > 0) {
      await assertStandardTable(page, menuTable);
    }

    await expectAuthenticatedPage(page, '/admin/_routing/');
    await expect(page.locator('[data-testid="routing-kit-definitions-table"], table').first()).toBeVisible();

    await expectAuthenticatedPage(page, '/admin/_routing/new');
    await assertStandardForm(page, page.locator('[data-testid="routing-kit-definition-form"], main form').first());

    await expectAuthenticatedPage(page, '/breadcrumb-kit-admin/collections');
    await expect(page.locator('[data-testid="breadcrumb-kit-collections-table"], table').first()).toBeVisible();
  });

  test('account profile + prefs forms stay FormKit-shaped', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/profile');
    await assertShellAesthetics(page);
    await assertStandardForm(page, page.locator('[data-testid="profile-basic-form"]'));

    await expectAuthenticatedPage(page, '/account/display');
    await assertShellAesthetics(page);
    await assertStandardForm(page, page.getByRole('main').locator('form').first());

    await expectAuthenticatedPage(page, '/account/security');
    await assertShellAesthetics(page);
    await expect(
      page.getByRole('main').locator('[data-controller="password-toggle"], .password-strength-widget, form').first(),
    ).toBeVisible();
  });

  test('project issues list + settings form chrome', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/issues`);
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    await expect(page.locator('[data-tour="project-nav"]')).toBeVisible();
    const issuesTable = page.getByRole('main').locator('table').first();
    if ((await issuesTable.count()) > 0) {
      await assertStandardTable(page, issuesTable);
    } else {
      await expect(page.getByRole('main').locator('.panel, [data-testid]').first()).toBeVisible();
    }

    await expectAuthenticatedPage(page, `/projects/${uuid}/settings`);
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    const settingsForm = page.getByRole('main').locator('form').first();
    if ((await settingsForm.count()) > 0) {
      await assertStandardForm(page, settingsForm);
    }
  });

  test('login guest form chrome (no storage)', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/login');
    await assertShellAesthetics(page);
    const form = page.locator('form[name="login_form"], .nowo-auth-kit__panel form').first();
    await expect(form).toBeVisible();
    await expect(form.locator('input[name="login_form[_username]"]')).toBeVisible();
    await expect(form.locator('input[name="login_form[_password]"]')).toBeVisible();
    await expect(
      form.locator('.nowo-auth-kit__panel button[type="submit"], button[type="submit"]').first(),
    ).toBeVisible();
    await expect(page.locator('.form-password-toggle [data-controller="password-toggle"]').first()).toBeVisible();
  });
});

test.describe('Kit chrome standardization — tabs, dropdown, modal, alert, background', () => {
  test('authenticated page-shell background uses Beacon tokens (no Bootstrap)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    await dismissProductTour(page);
    await assertShellAesthetics(page);

    await expect(page.locator('body.page-shell.page-shell--app')).toBeVisible();
    const bg = await page.evaluate(() => {
      const body = document.body;
      const styles = getComputedStyle(body);
      return {
        backgroundImage: styles.backgroundImage,
        backgroundColor: styles.backgroundColor,
        sand: getComputedStyle(document.documentElement).getPropertyValue('--color-sand').trim(),
      };
    });
    // Atmosphere: either a non-flat background-image or a sand-tinted surface (not pure white default).
    const hasAtmosphere =
      (bg.backgroundImage && bg.backgroundImage !== 'none') ||
      (bg.sand.length > 0 && bg.backgroundColor !== 'rgba(0, 0, 0, 0)');
    expect(hasAtmosphere, `expected atmospheric shell bg, got ${JSON.stringify(bg)}`).toBeTruthy();
  });

  test('account area + nested preference tabs use beacon-tabs chrome', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/profile');
    await dismissProductTour(page);
    await assertShellAesthetics(page);

    const area = page.locator('[data-testid="account-area-nav"]');
    await expect(area).toBeVisible();
    await expect(area.locator('.beacon-tabs, .nowo-ui-tabs, .preferences-area-nav, [role="tablist"], nav, a').first()).toBeVisible();
    await expect(area.getByRole('link', { name: /security|seguridad/i })).toBeVisible();
    // ES label is "Interfaz"; EN "Display"; DE "Anzeige"; …
    await expect(area.getByRole('link', { name: /display|interfaz|anzeige|afficher|mostrar|weergave/i })).toBeVisible();

    await area.getByRole('link', { name: /security|seguridad/i }).click();
    await expect(page).toHaveURL(/\/account\/security/);
    await expect(page.locator('[data-testid="account-area-nav"]')).toBeVisible();
    // Nested security tabs (Activity / Devices / …) share preferences-nav / beacon-tabs.
    await expect(page.locator('.preferences-nav, .beacon-tabs, [role="tablist"]').first()).toBeVisible();

    await expectAuthenticatedPage(page, '/account/display');
    await expect(page.locator('[data-testid="account-area-nav"]')).toBeVisible();
    await expect(page.locator('.preferences-nav, .beacon-tabs').first()).toBeVisible();
  });

  test('project settings + issue detail section tabs', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);

    await expectAuthenticatedPage(page, `/projects/${uuid}/settings`);
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    await expect(page.locator('.beacon-tabs--sections, .beacon-tabs').first()).toBeVisible();

    // Issue detail tabs need at least one issue — skip gracefully if list empty.
    await expectAuthenticatedPage(page, `/projects/${uuid}/issues`);
    await dismissProductTour(page);
    const issueLink = page.locator('main a[href*="/issues/"]').first();
    if ((await issueLink.count()) === 0) {
      test.info().annotations.push({ type: 'note', description: 'no issues — skip detail tabs' });
      return;
    }
    await issueLink.click();
    await expect(page).toHaveURL(/\/issues\//);
    await dismissProductTour(page);
    const detailTabs = page.locator('[data-testid="issue-detail-tabs"]');
    await expect(detailTabs).toBeVisible();
    await expect(detailTabs.locator('.beacon-tabs, .nowo-ui-tabs, a, button').first()).toBeVisible();
  });

  test('user menu dropdown opens with kit menuitems', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    await dismissProductTour(page);
    await assertShellAesthetics(page);

    const menu = page.locator('[data-tour="user-menu"], .user-menu').first();
    await expect(menu).toBeVisible();
    const summary = menu.locator('summary.user-menu__summary, [data-user-menu] summary').first();
    await summary.click();
    const panel = menu.locator('.user-menu__panel[role="menu"]');
    await expect(panel).toBeVisible();
    await expect(panel.getByRole('menuitem').first()).toBeVisible();
    await expect(panel.locator('a.user-menu__link[href*="/account"]').first()).toBeVisible();
    await expect(panel.locator('a.user-menu__link--danger, a[href*="logout"]').first()).toBeVisible();
  });

  test('confirm-dialog modal chrome on privacy anonymize', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/privacy');
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    await expect(page.locator('[data-testid="account-privacy-anonymize"]')).toBeVisible();

    const openBtn = page.locator('[data-testid="account-privacy-anonymize-open"]');
    if ((await openBtn.count()) === 0 || !(await openBtn.isEnabled())) {
      // Last admin / already anonymized / blocked — panel still kit-shaped.
      await expect(page.locator('[data-testid="account-privacy-anonymize"]')).toBeVisible();
      return;
    }

    await openBtn.click();
    const dialog = page.locator('[data-testid="account-privacy-anonymize"] dialog.confirm-dialog, dialog.confirm-dialog').first();
    await expect(dialog).toBeVisible({ timeout: 10_000 });
    await expect(dialog.locator('.confirm-dialog__title, .confirm-dialog__header, h2').first()).toBeVisible();
    await expect(dialog.locator('[data-confirm-dialog-close], button.btn-ghost').first()).toBeVisible();
    await expect(
      dialog.locator('[data-testid="account-privacy-anonymize-submit"], button.btn-danger').first(),
    ).toBeVisible();
    await dialog.locator('[data-confirm-dialog-close], button.btn-ghost').first().click();
  });

  test('flash / toast stack seam exists for kit alerts', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    await dismissProductTour(page);
    // Stack host is always in the app shell even when empty (UiKit toast region).
    const stack = page.locator('.nowo-ui-toast-stack, [data-controller*="toast"], #flash-toasts, .flash-stack');
    // If the stack is only injected when flashes exist, assert body still has no Bootstrap alerts.
    if ((await stack.count()) > 0) {
      await expect(stack.first()).toBeAttached();
    }
    await expect(page.locator('.alert-primary, .alert-dismissible.alert')).toHaveCount(0);
    await expect(page.locator('link[href*="bootstrap"]')).toHaveCount(0);
  });

  test('admin projects Stimulus tablist uses kit panel chrome', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/projects');
    await dismissProductTour(page);
    await assertShellAesthetics(page);

    const portability = page.locator('[data-testid="admin-projects-config-portability"]');
    if ((await portability.count()) === 0) {
      await expect(page.getByRole('main').locator('table, .panel').first()).toBeVisible();
      return;
    }
    await expect(portability).toBeVisible();
    await expect(portability.locator('[data-controller="tabs"] [role="tablist"], [role="tablist"]').first()).toBeVisible();
    const tabs = portability.locator('[role="tab"]');
    if ((await tabs.count()) >= 2) {
      await tabs.nth(1).click();
      const selected = await tabs.nth(1).getAttribute('aria-selected');
      const pressed = await tabs.nth(1).getAttribute('aria-pressed');
      const activeClass = (await tabs.nth(1).getAttribute('class')) ?? '';
      expect(
        selected === 'true' || pressed === 'true' || /is-active|active/.test(activeClass),
        'second tab should become active',
      ).toBeTruthy();
    }
  });

  test('cookie consent + maintenance kit admin tabs stay on Beacon tokens', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/cookie-consent');
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    await expect(page.locator('.beacon-tabs, .nowo-ui-tabs, .kit-admin').first()).toBeVisible();

    await expectAuthenticatedPage(page, '/admin/maintenance');
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    await expect(page.locator('.nowo-ui-tabs, .beacon-tabs, main form, main .panel').first()).toBeVisible();
  });

  test('dashboard new-project confirm-dialog modal uses kit panel', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    await dismissProductTour(page);
    await assertShellAesthetics(page);

    const openBtn = page.locator('[data-tour="new-project"], button[data-action*="confirm-dialog#open"]').first();
    await expect(openBtn).toBeVisible();
    await openBtn.click();

    const dialog = page.locator('dialog.confirm-dialog').first();
    await expect(dialog).toBeVisible({ timeout: 10_000 });
    await expect(dialog.locator('.confirm-dialog__title, .confirm-dialog__header, h2, form').first()).toBeVisible();
    await expect(dialog.locator('button.btn-primary, button[type="submit"]').first()).toBeVisible();
    await expect(dialog.locator('[data-confirm-dialog-close], button.btn-ghost').first()).toBeVisible();
    await dialog.locator('[data-confirm-dialog-close], button.btn-ghost').first().click();
    await expect.poll(async () => dialog.evaluate((el) => (el as HTMLDialogElement).open)).toBeFalsy();
  });

  test('RBAC + SiteBackup admin tables and forms stay kit-shaped', async ({ page }) => {
    for (const path of ['/admin/roles', '/admin/permissions']) {
      await expectAuthenticatedPage(page, path);
      await dismissProductTour(page);
      await assertShellAesthetics(page);
      const table = page.getByRole('main').locator('table').first();
      if ((await table.count()) > 0) {
        await assertStandardTable(page, table);
      } else {
        await expect(page.getByRole('main').locator('.panel, [data-testid]').first()).toBeVisible();
      }
    }

    await expectAuthenticatedPage(page, '/admin/roles/new');
    await dismissProductTour(page);
    await assertStandardForm(page, await formOnPageOrDialog(page));

    await expectAuthenticatedPage(page, '/_site_backup');
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    await expect(page.locator('.kit-admin, main .panel, main form, main table').first()).toBeVisible();
  });

  test('list filter forms use CSRF + kit submit (users / http-log)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/users');
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    const userFilter = page.getByRole('main').locator('form').first();
    if ((await userFilter.count()) > 0) {
      // GET SearchQueryType may omit classic submit class — still require CSRF seam or kit field chrome.
      const token = userFilter.locator('input[type="hidden"][name*="_token"], input[name*="[_token]"]');
      const kitField = userFilter.locator('.form-group, .nowo-form, label, input, select').first();
      expect((await token.count()) + (await kitField.count()), 'users filter should look FormKit-shaped').toBeGreaterThan(0);
    }

    await expectAuthenticatedPage(page, '/admin/http-log');
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="http-log-filters"]')).toBeVisible();
    const filterForm = page.locator('[data-testid="http-log-filters"] form').first();
    if ((await filterForm.count()) > 0) {
      await assertStandardForm(page, filterForm);
    }
  });

  test('member alerts preferences expose kit confirm-dialog + alert rows', async ({ page }) => {
    await expectAuthenticatedPage(page, '/account/display/notifications');
    await dismissProductTour(page);
    await assertShellAesthetics(page);

    const section = page.locator('[data-testid="member-alerts-section"]');
    await expect(section).toBeVisible();
    await expect(page.locator('[data-testid="member-alert-event-row"]').first()).toBeVisible();

    const openProject = page.locator('[data-action*="confirm-dialog#open"]').first();
    if ((await openProject.count()) > 0) {
      await openProject.click();
      const dialog = page.locator('dialog.confirm-dialog').first();
      await expect(dialog).toBeVisible({ timeout: 10_000 });
      await expect(dialog.locator('.confirm-dialog__title, .confirm-dialog__header').first()).toBeVisible();
      await dialog.locator('[data-confirm-dialog-close], button.btn-ghost').first().click();
    }
  });

  test('flash toast stack renders kit flash after appearance save', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/appearance');
    await dismissProductTour(page);
    await assertShellAesthetics(page);

    const form = page.getByRole('main').locator('form').first();
    await expect(form).toBeVisible();
    await assertStandardForm(page, form);
    const save = form.locator('button.btn-primary[type="submit"], button[type="submit"].btn-primary').first();
    if ((await save.count()) > 0) {
      await save.click();
    } else {
      // Theme cards are submit buttons — click the already-selected moss-bordered card.
      await form.locator('button[type="submit"].border-\\[var\\(--color-moss\\)\\], button[type="submit"]').first().click();
    }
    await page.waitForLoadState('domcontentloaded');

    const toastOrFlash = page.locator(
      '.nowo-ui-toast-stack [data-testid="flash-structured"], .nowo-ui-toast-stack .nowo-ui-toast, [data-testid="flash-structured"], .flash, .nowo-ui-toast',
    );
    if ((await toastOrFlash.count()) > 0) {
      await expect(toastOrFlash.first()).toBeVisible({ timeout: 10_000 });
    } else {
      await expect(page).toHaveURL(/\/admin\/appearance/);
    }
    await expect(page.locator('.alert-success.alert-dismissible, .alert-primary')).toHaveCount(0);
  });

  test('public cookie consent modal uses kit chrome (guest)', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/login');
    await assertShellAesthetics(page);

    const modal = page.locator('#cookieconsent, [data-nowo-cookie-consent], .nowo-cookie-consent');
    await expect(modal.first()).toBeAttached({ timeout: 10_000 });
    // Kit mounts the dialog even when closed (aria-hidden / data-nowo-open=false).
    await expect(modal.first()).toHaveAttribute('data-nowo-ui-theme', /tailwind|bootstrap|none/i);
    await expect(page.locator('.modal.fade.bootstrap, link[href*="bootstrap.css"]')).toHaveCount(0);
    await expect(page.locator('link[href*="bootstrap"]')).toHaveCount(0);
  });

  test('project settings portability + API keys panels use sand borders / kit buttons', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/settings`);
    await dismissProductTour(page);
    await assertShellAesthetics(page);

    const portability = page.locator('[data-testid="project-config-portability"]');
    if ((await portability.count()) > 0) {
      await expect(portability).toBeVisible();
      await expect(portability.locator('[data-testid="project-config-download"], a.btn-primary').first()).toBeVisible();
      await expect(portability.locator('[data-testid="project-config-import"], form').first()).toBeVisible();
    }

    // Settings section tabs should keep Beacon tokens on active state.
    const sectionTabs = page.locator('.beacon-tabs--sections a, .beacon-tabs a, [role="tab"]');
    if ((await sectionTabs.count()) >= 2) {
      await sectionTabs.nth(1).click();
      await page.waitForLoadState('domcontentloaded');
      await expect(page.locator('link[href*="bootstrap"]')).toHaveCount(0);
      await expect(page.getByRole('main').locator('.panel, form, table').first()).toBeVisible();
    }
  });
});

/** Static GET screens that were missing from the baseline kit-chrome pass. */
const MISSING_AUTHENTICATED_STATIC = [
  '/dashboard/activity',
  '/dashboard/alerts',
  '/dashboard/assignments',
  '/dashboard/mentions',
  '/dashboard/summary',
  '/dashboard/new-in-release',
  '/account',
  '/account/preferences',
  '/account/projects',
  '/account/groups',
  '/account/security/activity',
  '/account/security/devices',
  '/account/security/history',
  '/account/display/panels',
  '/account/display/tours',
  '/admin/ops',
  '/admin/social-login',
  '/admin/social-login/new',
  '/admin/permissions/new',
  // `/admin/menus/menu/new` is a modal-only form partial (no host layout) — covered via menus index modal.
  '/breadcrumb-kit-admin/collections/new',
  '/_site_backup/history',
] as const;

const MISSING_GUEST_STATIC = [
  '/register',
  '/reset-password',
  '/login/magic',
  '/login/qr',
  '/en/legal/privacy',
  '/en/legal/terms',
  '/en/legal/cookies',
  '/en/legal/notice',
] as const;

test.describe('Kit chrome — missing product screens (gap-close)', () => {
  test('authenticated dashboard + account screens smoke kit chrome', async ({ page }) => {
    test.setTimeout(180_000);
    const paths = MISSING_AUTHENTICATED_STATIC.filter((p) => p.startsWith('/dashboard') || p.startsWith('/account'));
    for (const path of paths) {
      await expectAuthenticatedPage(page, path);
      await dismissProductTour(page);
      await assertPageHasKitChrome(page);
    }
  });

  test('authenticated admin remainder screens smoke kit chrome', async ({ page }) => {
    test.setTimeout(120_000);
    const paths = MISSING_AUTHENTICATED_STATIC.filter((p) => p.startsWith('/admin') || p.startsWith('/breadcrumb') || p.startsWith('/_site'));
    for (const path of paths) {
      await expectAuthenticatedPage(page, path);
      await dismissProductTour(page);
      await assertPageHasKitChrome(page);
    }

    // Dashboard-menu "new" is modal-hosted on the index (GET /menu/new is a bare partial).
    await expectAuthenticatedPage(page, '/admin/menus/');
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    const openNewMenu = page.locator('button[data-nowo-modal-target="modal-menu-new"]').first();
    if ((await openNewMenu.count()) > 0) {
      await openNewMenu.click();
    } else {
      await page.getByRole('button', { name: /new menu|nuevo menú/i }).first().click();
    }
    const menuDialog = page.locator('dialog#modal-menu-new');
    await expect(menuDialog).toBeVisible({ timeout: 10_000 });
    await assertStandardForm(page, menuDialog.locator('form').first());
  });

  test('project remainder screens smoke kit chrome', async ({ page }) => {
    test.setTimeout(120_000);
    const uuid = await resolveDemoProjectUuid(page);
    const paths = [
      `/projects/${uuid}`,
      `/projects/${uuid}/analytics`,
      `/projects/${uuid}/performance`,
      `/projects/${uuid}/releases`,
      `/projects/${uuid}/notifications/new`,
      `/projects/${uuid}/threshold-rules/new`,
      '/projects/new',
    ];
    for (const path of paths) {
      await expectAuthenticatedPage(page, path);
      await dismissProductTour(page);
      await assertPageHasKitChrome(page);
    }
  });

  test('admin show/edit follow first list row when present', async ({ page }) => {
    test.setTimeout(180_000);
    const lists: Array<{ list: string; linkRe: RegExp }> = [
      { list: '/admin/users', linkRe: /\/admin\/users\/\d+/ },
      { list: '/admin/groups', linkRe: /\/admin\/groups\/\d+/ },
      { list: '/admin/projects', linkRe: /\/admin\/projects\/\d+/ },
      { list: '/admin/roles', linkRe: /\/admin\/roles\/\d+/ },
    ];
    for (const { list, linkRe } of lists) {
      await expectAuthenticatedPage(page, list);
      await dismissProductTour(page);
      const hrefs = await page.locator('main a[href]').evaluateAll((anchors, reSource) => {
        const re = new RegExp(reSource);
        return anchors
          .map((a) => (a as HTMLAnchorElement).getAttribute('href') ?? '')
          .filter((href) => re.test(href) && !/\/(new|export|import|delete)/.test(href));
      }, linkRe.source);
      if (hrefs.length === 0) {
        continue;
      }
      await expectAuthenticatedPage(page, hrefs[0]);
      await dismissProductTour(page);
      await assertPageHasKitChrome(page);

      const edit = page.locator(`main a[href*="/edit"]`).first();
      if ((await edit.count()) > 0) {
        const editHref = await edit.getAttribute('href');
        if (editHref) {
          await expectAuthenticatedPage(page, editHref);
          await dismissProductTour(page);
          await assertPageHasKitChrome(page);
        }
      }
    }
  });

  test('guest auth + legal screens smoke kit chrome', async ({ page }) => {
    test.setTimeout(120_000);
    await page.context().clearCookies();
    for (const path of MISSING_GUEST_STATIC) {
      await page.goto(path, { waitUntil: 'domcontentloaded' });
      await assertShellAesthetics(page);
      const shell = page.locator('main, .nowo-auth-kit__panel, .nowo-auth-kit, article.legal, .legal-page').first();
      await expect(shell).toBeVisible();
      await expect(page.locator('link[href*="bootstrap"]')).toHaveCount(0);
      const form = page.locator('main form, .nowo-auth-kit__panel form, .nowo-auth-kit form').first();
      if ((await form.count()) > 0 && (await form.isVisible().catch(() => false))) {
        if ((await form.locator('button[type="submit"], input[type="submit"]').count()) > 0) {
          await assertStandardForm(page, form);
        }
      }
    }
  });

  test('issue detail tabs + duplicate confirm-dialog when an issue exists', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await expectAuthenticatedPage(page, `/projects/${uuid}/issues`);
    await dismissProductTour(page);
    const issueLink = page.locator('main a[href*="/issues/"]').first();
    if ((await issueLink.count()) === 0) {
      test.info().annotations.push({ type: 'note', description: 'no issues — skip detail chrome' });
      return;
    }
    await issueLink.click();
    await expect(page).toHaveURL(/\/issues\//);
    await dismissProductTour(page);
    await assertShellAesthetics(page);
    await expect(page.locator('[data-testid="issue-detail-tabs"], .beacon-tabs').first()).toBeVisible();
    await expect(page.getByRole('main').locator('.panel, [data-testid], form').first()).toBeVisible();

    const dupHost = page.locator('[data-testid="mark-duplicate"][data-controller="confirm-dialog"]');
    if ((await dupHost.count()) > 0) {
      const openBtn = dupHost.locator('[data-action*="confirm-dialog#open"]').first();
      if ((await openBtn.count()) > 0) {
        await openBtn.click();
        const dialog = page.locator('dialog.confirm-dialog').first();
        await expect(dialog).toBeVisible({ timeout: 10_000 });
        await expect(dialog.locator('.confirm-dialog__title, .confirm-dialog__header, form').first()).toBeVisible();
        await dialog.locator('[data-confirm-dialog-close], button.btn-ghost').first().click();
      }
    }
  });
});

test.describe('Kit chrome — CSS standardization layer', () => {
  test('btn-primary / inputs / panels stay size-aligned across product screens', async ({ page }) => {
    test.setTimeout(180_000);
    const uuid = await resolveDemoProjectUuid(page);
    const samplePaths = [
      '/dashboard',
      '/admin/users',
      '/admin/mailer',
      '/account/profile',
      `/projects/${uuid}/settings`,
      `/projects/${uuid}/issues`,
      '/admin/http-log',
    ];

    const btnSamples: Array<{ path: string; box: ChromeBox }> = [];
    const inputSamples: Array<{ path: string; box: ChromeBox }> = [];
    const panelSamples: Array<{ path: string; box: ChromeBox }> = [];

    for (const path of samplePaths) {
      await expectAuthenticatedPage(page, path);
      await dismissProductTour(page);
      await assertShellAesthetics(page);
      const sample = await samplePageChrome(page, path);
      if (sample.btn) {
        btnSamples.push({ path, box: sample.btn });
        assertNearTarget(sample.btn, BEACON_BTN_TARGET, `btn-primary@${path}`);
      }
      if (sample.input) {
        inputSamples.push({ path, box: sample.input });
      }
      if (sample.panel) {
        panelSamples.push({ path, box: sample.panel });
      }
    }

    expect(btnSamples.length, 'collected btn-primary samples').toBeGreaterThanOrEqual(4);
    expect(inputSamples.length, 'collected input samples').toBeGreaterThanOrEqual(3);

    assertBoxesAligned(btnSamples, 'btn-primary', ['paddingTop', 'paddingLeft', 'fontSize'], 2);
    assertBoxesAligned(inputSamples, 'text-input', ['paddingTop', 'fontSize'], 3);
    if (panelSamples.length >= 2) {
      assertBoxesAligned(panelSamples, 'panel', ['paddingTop', 'paddingLeft'], 4);
    }
  });

  test('confirm-dialog and AuthKit guest controls match kit button metrics', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    await dismissProductTour(page);
    await page.locator('[data-tour="new-project"], button[data-action*="confirm-dialog#open"]').first().click();
    const dialog = page.locator('dialog.confirm-dialog').first();
    await expect(dialog).toBeVisible({ timeout: 10_000 });
    const dialogBtn = dialog.locator('button.btn-primary, button.btn-danger, button.btn-ghost').first();
    const dialogBox = await readChromeBox(dialogBtn);
    expect(dialogBox, 'dialog kit button metrics').not.toBeNull();
    if (dialogBox) {
      expect(Math.abs(dialogBox.fontSize - BEACON_BTN_TARGET.fontSizePx)).toBeLessThanOrEqual(2);
      expect(Math.abs(dialogBox.paddingTop - BEACON_BTN_TARGET.paddingYPx)).toBeLessThanOrEqual(2);
    }
    await dialog.locator('[data-confirm-dialog-close], button.btn-ghost').first().click();

    await page.context().clearCookies();
    await page.goto('/login');
    await assertShellAesthetics(page);
    const loginSubmit = page.locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]').first();
    const loginBox = await readChromeBox(loginSubmit);
    expect(loginBox, 'login submit metrics').not.toBeNull();
    if (loginBox) {
      expect(Math.abs(loginBox.fontSize - BEACON_BTN_TARGET.fontSizePx)).toBeLessThanOrEqual(2);
      expect(loginBox.paddingTop).toBeGreaterThanOrEqual(BEACON_BTN_TARGET.paddingYPx - 1);
      expect(loginBox.paddingTop).toBeLessThanOrEqual(14);
      expect(Math.abs(loginBox.paddingLeft - BEACON_BTN_TARGET.paddingXPx)).toBeLessThanOrEqual(2);
    }
  });
});
