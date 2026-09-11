import { expect, test, type Locator, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const DEMO_EMAIL = process.env.PLAYWRIGHT_DEMO_EMAIL ?? 'admin@symfony-beacon.local';
export const DEMO_PASSWORD = process.env.PLAYWRIGHT_DEMO_PASSWORD ?? 'admin123';
/** Seeded demo admin phone (E.164) — AuthKit QR approve requires a verified number. */
export const DEMO_PHONE_COUNTRY = 'ES';
export const DEMO_PHONE_NATIONAL = '600000000';

const helpersDir = path.dirname(fileURLToPath(import.meta.url));

/** Demo ingest credentials from `make seed` (`.demo-client.env`) or isolated `make ready-e2e` (`.demo-client.e2e.env`). */
export type DemoIngestCredentials = {
  projectId: string;
  publicKey: string;
  secretKey: string;
  projectUuid?: string;
};

/**
 * Parse `.demo-client.env` written by `app:seed-demo`.
 * Returns null when missing (callers should `requireSampleOrSkip`).
 */
export function loadDemoIngestCredentials(): DemoIngestCredentials | null {
  const isolated = process.env.PLAYWRIGHT_ISOLATED === '1';
  const envCandidates = isolated
    ? [
        path.join(helpersDir, '..', '..', '.demo-client.e2e.env'),
        path.join(helpersDir, '..', '.demo-client.env.cache'),
        path.join(helpersDir, '.demo-client.env.cache'),
      ]
    : [
        path.join(helpersDir, '..', '..', '.demo-client.env'),
        path.join(helpersDir, '..', '.demo-client.env.cache'),
        path.join(helpersDir, '.demo-client.env.cache'),
      ];
  let envText = '';
  for (const envPath of envCandidates) {
    try {
      if (fs.existsSync(envPath)) {
        envText = fs.readFileSync(envPath, 'utf8');
        break;
      }
    } catch {
      // continue
    }
  }
  if (!envText) {
    return null;
  }

  const dsnMatch = envText.match(/^BEACON_DSN=(.+)$/m) ?? envText.match(/^BEACON_UI_DSN=(.+)$/m);
  const publicKeyMatch = envText.match(/^BEACON_PUBLIC_KEY=(.+)$/m);
  const projectIdMatch = envText.match(/^BEACON_PROJECT_ID=(\d+)$/m);
  const projectUuidMatch = envText.match(/^BEACON_PROJECT_UUID=([0-9a-f-]{36})$/im);

  let projectId = projectIdMatch?.[1];
  let publicKey = publicKeyMatch?.[1]?.trim();
  let secretKey: string | undefined;
  let projectUuid = projectUuidMatch?.[1];

  if (dsnMatch?.[1]) {
    const raw = dsnMatch[1].trim().replace(/^["']|["']$/g, '');
    try {
      const url = new URL(raw.replace(/^beacon:/i, 'http:'));
      publicKey = publicKey ?? decodeURIComponent(url.username);
      secretKey = decodeURIComponent(url.password);
      const id = url.pathname.replace(/^\//, '').split('/')[0];
      if (/^\d+$/.test(id)) {
        projectId = projectId ?? id;
      } else if (/^[0-9a-f-]{36}$/i.test(id)) {
        projectUuid = projectUuid ?? id;
      }
    } catch {
      const parsed = raw.match(/^(?:beacon|http|https):\/\/([^:]+):([^@]+)@[^/]+\/([^/\s]+)/i);
      if (parsed) {
        publicKey = publicKey ?? parsed[1];
        secretKey = parsed[2];
        if (/^\d+$/.test(parsed[3])) {
          projectId = projectId ?? parsed[3];
        } else if (/^[0-9a-f-]{36}$/i.test(parsed[3])) {
          projectUuid = projectUuid ?? parsed[3];
        }
      }
    }
  }

  // Envelope/OTLP accept numeric project id or UUID; prefer numeric when present.
  const ref = projectId ?? projectUuid;
  if (!ref || !publicKey || !secretKey) {
    return null;
  }

  return { projectId: ref, publicKey, secretKey, projectUuid };
}

/** Preferred HTTP ingest base (Docker clients use :9084; isolated E2E uses :9085). */
export function ingestHttpBase(): string {
  if (process.env.PLAYWRIGHT_INGEST_BASE_URL) {
    return process.env.PLAYWRIGHT_INGEST_BASE_URL;
  }
  if (process.env.PLAYWRIGHT_ISOLATED === '1') {
    return 'http://localhost:9085';
  }
  return 'http://localhost:9084';
}

export function beaconAuthHeader(publicKey: string, secretKey: string): string {
  return `Beacon beacon_key=${publicKey}, beacon_secret=${secretKey}`;
}

/** CI / PLAYWRIGHT_REQUIRE_SAMPLE=1: missing demo sample data fails instead of skip. */
export function requireSampleOrSkip(ready: boolean, reason: string): void {
  if (ready) {
    return;
  }
  if (process.env.CI || process.env.PLAYWRIGHT_REQUIRE_SAMPLE === '1') {
    throw new Error(reason);
  }
  test.skip(true, reason);
}

/** Navigate with retries for transient WSL/Docker net::ERR_NETWORK_CHANGED / chrome-error. */
export async function gotoStable(page: Page, path: string, attempts = 5): Promise<void> {
  let lastError: unknown;
  for (let attempt = 0; attempt < attempts; attempt++) {
    try {
      await page.goto(path, { waitUntil: 'domcontentloaded' });
      const url = page.url();
      if (url.startsWith('chrome-error://') || url.startsWith('chrome-error:')) {
        throw new Error(`Navigation landed on chrome-error for ${path}`);
      }
      return;
    } catch (err) {
      lastError = err;
      const msg = String(err);
      const retryable =
        /ERR_NETWORK_CHANGED|net::ERR_|chrome-error|Navigation landed on chrome-error|Timeout/i.test(msg);
      if (attempt === attempts - 1 || !retryable) {
        throw err;
      }
      await page.waitForTimeout(400 * (attempt + 1));
    }
  }
  throw lastError;
}

/** Wait for the Beacon page-loader overlay to release pointer events. */
export async function waitForPageLoader(page: Page): Promise<void> {
  const loader = page.locator('.page-loader.is-active, [data-controller="page-loader"].is-active');
  await loader.waitFor({ state: 'hidden', timeout: 15_000 }).catch(() => undefined);
}

/** Dismiss cookie consent only when the modal is actually open. */
export async function dismissCookieConsent(page: Page): Promise<void> {
  await waitForPageLoader(page);
  const openModal = page.locator('#cookieconsent[data-nowo-open="true"]:not(.hidden)');
  try {
    await openModal.waitFor({ state: 'visible', timeout: 3_000 });
  } catch {
    return;
  }

  const acceptAll = openModal.locator(
    '#cookie_consent_use_all_cookies, button:has-text("Accept all"), button:has-text("Aceptar todas")',
  );
  const functionalOnly = openModal.locator(
    '#cookie_consent_use_only_functional_cookies, button:has-text("necessary"), button:has-text("necesarias"), button:has-text("Solo cookies")',
  );

  const target = (await acceptAll.first().isVisible().catch(() => false)) ? acceptAll.first() : functionalOnly.first();
  await target.waitFor({ state: 'visible', timeout: 5_000 });
  await expect(target).toBeEnabled();
  await target.evaluate((el: HTMLElement) => el.click());

  await openModal.waitFor({ state: 'hidden', timeout: 10_000 }).catch(async () => {
    // Fallback: set consent cookies so subsequent navigations skip the modal.
    await page.context().addCookies([
      { name: 'Cookie_Consent', value: 'true', url: page.url() },
      { name: 'Cookie_Consent_Key', value: 'e2e', url: page.url() },
    ]);
  });
}

/** Close driver.js product tour when present. */
export async function dismissProductTour(page: Page): Promise<void> {
  await waitForPageLoader(page);
  const popover = page.locator('.driver-popover, .beacon-driver-popover');
  try {
    await popover.first().waitFor({ state: 'visible', timeout: 2_000 });
  } catch {
    return;
  }

  for (let i = 0; i < 12; i++) {
    if (!(await popover.first().isVisible().catch(() => false))) {
      return;
    }
    const next = popover
      .locator(
        'button.driver-popover-next-btn, button.driver-popover-done-btn, button.driver-popover-close-btn, button:has-text("Done"), button:has-text("Skip"), button:has-text("Close"), button:has-text("Omitir"), button:has-text("Listo"), button:has-text("Siguiente"), button:has-text("Next")',
      )
      .first();
    if (await next.isVisible().catch(() => false)) {
      await next.click({ force: true }).catch(() => undefined);
      await popover.first().waitFor({ state: 'hidden', timeout: 500 }).catch(() => undefined);
      continue;
    }
    await page.keyboard.press('Escape').catch(() => undefined);
    await popover.first().waitFor({ state: 'hidden', timeout: 500 }).catch(() => undefined);
  }
}

/**
 * Exit admin “view-as-member” if a prior test left `_beacon_view_as_member` in the shared PHP session.
 * That mode blanks FormKit fields and denies project settings (403).
 * Caller must be on a page that renders the banner (e.g. /dashboard), not a 403 shell.
 */
export async function exitViewAsMember(page: Page): Promise<void> {
  const form = page.locator('form[action*="/admin/view-as-member/disable"]').first();
  if ((await form.count()) === 0 || !(await form.isVisible().catch(() => false))) {
    return;
  }
  // Prefer request POST with the rendered CSRF fields — button clicks flake under overlays.
  const action = (await form.getAttribute('action')) || '/admin/view-as-member/disable';
  const fields: Record<string, string> = {};
  for (const input of await form.locator('input').all()) {
    const name = await input.getAttribute('name');
    if (!name) {
      continue;
    }
    fields[name] = await input.inputValue();
  }
  const response = await page.request.post(action, { form: fields, maxRedirects: 0, failOnStatusCode: false });
  expect([302, 303, 200].includes(response.status()), `view-as-member disable status=${response.status()}`).toBeTruthy();
  await page.reload({ waitUntil: 'domcontentloaded' });
  await dismissProductTour(page);
}

/**
 * Restore demo admin phone + phoneVerifiedAt after profile E2E clears verification.
 * Non-prod GET; 204 when the admin session can repair the QR approver.
 */
export async function ensureDemoQrApprover(page: Page): Promise<void> {
  const response = await page.request.get('/_internal/demo/ensure-qr-approver', { failOnStatusCode: false });
  expect(response.status(), await response.text()).toBe(204);
}

export async function loginAsDemo(page: Page, email = DEMO_EMAIL, password = DEMO_PASSWORD): Promise<void> {
  await gotoStable(page, '/login');
  await dismissCookieConsent(page);

  await page.locator('input[name="login_form[_username]"]').fill(email);
  await page.locator('input[name="login_form[_password]"]').fill(password);
  await page.locator('.nowo-auth-kit__panel button[type="submit"], form[name="login_form"] button[type="submit"]').first().click();

  await page.waitForURL(/\/dashboard(\?|$)/, { timeout: 30_000 });
  await dismissProductTour(page);
}

/** Resolve demo project UUID from dashboard project cards. */
export async function resolveDemoProjectUuid(page: Page): Promise<string> {
  if (!page.url().includes('/dashboard') || page.url().includes('/login')) {
    await gotoStable(page, '/dashboard');
  }
  await dismissProductTour(page);

  if (page.url().includes('/login')) {
    throw new Error('Not authenticated — shared storageState may have been invalidated (avoid logout in parallel suite).');
  }

  // Prefer the seeded demo project — ephemeral "E2E Project …" cards may sort first.
  const demoLink = page
    .locator('a[href*="/projects/"]')
    .filter({ hasText: /Symfony Beacon/i })
    .filter({ hasNot: page.locator('[href*="/projects/new"]') })
    .first();
  const fallback = page.locator('a[href*="/projects/"]').filter({ hasNot: page.locator('[href*="/projects/new"]') }).first();
  const link = (await demoLink.count()) > 0 ? demoLink : fallback;

  await expect(link).toBeVisible({ timeout: 20_000 });
  const href = await link.getAttribute('href');
  const match = href?.match(/\/projects\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not resolve project UUID from href: ${href ?? '(null)'}`);
  }
  return match[1];
}

/** Assert page did not land on login and returned a successful HTML response. */
export async function expectAuthenticatedPage(page: Page, path: string): Promise<void> {
  await gotoStable(page, path);
  await dismissProductTour(page);
  if (await page.locator('form[action*="/admin/view-as-member/disable"]').count()) {
    await exitViewAsMember(page);
    await gotoStable(page, path);
    await dismissProductTour(page);
  }
  if (/\/login(\?|$|\/)/i.test(page.url()) || page.url() === '' || page.url() === 'about:blank') {
    // Session can drop mid-suite under load; re-auth once then retry the path.
    await loginAsDemo(page);
    await gotoStable(page, path);
    await dismissProductTour(page);
    if (await page.locator('form[action*="/admin/view-as-member/disable"]').count()) {
      await exitViewAsMember(page);
      await gotoStable(page, path);
      await dismissProductTour(page);
    }
  }
  await expect(page, `Expected auth for ${path} (url=${page.url()})`).not.toHaveURL(/\/login(\?|$|\/)/);
  await expect(page.locator('body')).toBeVisible();
}

/**
 * Open the New threshold rule confirm-dialog on Settings → Alerts
 * (GET /threshold-rules/new redirects here with ?new_threshold=1).
 */
export async function openNewThresholdRuleForm(page: Page, projectUuid: string): Promise<Locator> {
  await gotoStable(page, `/projects/${projectUuid}/settings/alerts?new_threshold=1`);
  await dismissProductTour(page);
  const form = page.locator('[data-testid="project-threshold-create-form"]');
  await expect(form).toBeVisible({ timeout: 15_000 });
  return form;
}

/**
 * Open Administration create modal for groups or projects
 * (GET …/new redirects to the directory with ?new=1).
 */
export async function openAdminCreateForm(page: Page, kind: 'groups' | 'projects'): Promise<Locator> {
  await gotoStable(page, `/admin/${kind}?new=1`);
  await dismissProductTour(page);
  const form = page.locator(`form[action$="/admin/${kind}/new"]`);
  await expect(form).toBeVisible({ timeout: 15_000 });
  return form;
}

export async function expectGuestPage(page: Page, path: string): Promise<void> {
  const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
  await dismissCookieConsent(page);
  expect(response, `No response for ${path}`).not.toBeNull();
  const status = response!.status();
  expect(status, `${path} returned ${status}`).toBeLessThan(400);
  await expect(page.locator('body')).toBeVisible();
}

export async function logout(page: Page): Promise<void> {
  const menu = page.locator('[data-tour="user-menu"], [data-user-menu]');
  await menu.locator('summary').click();
  const logoutLink = menu.locator('a[href*="/logout"]');
  await expect(logoutLink).toBeVisible();
  await Promise.all([
    page.waitForURL(/\/login(\?|$|\/)/, { timeout: 30_000 }),
    logoutLink.click(),
  ]);
}

/** Parsed one-shot API key DSN after create/rotate (public:secret@host/ref). */
export type ParsedApiKeyDsn = {
  publicKey: string;
  secretKey: string;
  projectRef: string;
  raw: string;
};

const API_KEY_DSN_RE = /https?:\/\/([^:]+):([^@]+)@[^/\s]+\/([^\s"']+)/i;

/**
 * Wait for the create/rotate DSN reveal.
 * Spec 102 attaches the one-shot DSN to the matching key row (`api-key-dsn-once`);
 * the legacy flash banner (`api-key-dsn-flash`) only appears when no row matched.
 */
export async function waitForApiKeyDsnReveal(page: Page): Promise<ParsedApiKeyDsn> {
  const reveal = page.locator('[data-testid="api-key-dsn-once"], [data-testid="api-key-dsn-flash"]').first();
  await expect(reveal).toBeVisible({ timeout: 15_000 });

  // Prefer the Stimulus secret attribute (display may still show a masked value).
  const secretAttr = (await reveal.getAttribute('data-temporary-reveal-secret-value'))?.trim() ?? '';
  const displayText = (await reveal.locator('[data-testid="api-key-dsn"]').innerText().catch(() => '')).trim();
  const raw = secretAttr || displayText;
  const dsnMatch = raw.match(API_KEY_DSN_RE);
  expect(dsnMatch, 'DSN in create/rotate reveal').toBeTruthy();

  return {
    publicKey: dsnMatch![1],
    secretKey: dsnMatch![2],
    projectRef: dsnMatch![3],
    raw: dsnMatch![0],
  };
}

/** Create an API key on Settings → Access and parse the one-shot DSN. */
export async function createApiKeyAndParseDsn(page: Page, projectUuid: string, label: string): Promise<ParsedApiKeyDsn> {
  await page.goto(`/projects/${projectUuid}/settings/access`);
  await dismissProductTour(page);
  const createForm = page.locator('form').filter({ has: page.locator('input[name="project_api_key_create[label]"]') });
  await createForm.locator('input[name="project_api_key_create[label]"]').fill(label);
  await createForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
  await waitForPageLoader(page);

  return waitForApiKeyDsnReveal(page);
}

/** Open the first issue detail for a project; returns issue UUID or null. */
export async function openFirstIssue(page: Page, projectUuid: string): Promise<string | null> {
  await gotoStable(page, `/projects/${projectUuid}/issues`);
  await dismissProductTour(page);
  const issueLink = page.locator(`a[href*="/projects/${projectUuid}/issues/"]`).first();
  if ((await issueLink.count()) === 0) {
    return null;
  }
  const href = await issueLink.getAttribute('href');
  const match = href?.match(/\/issues\/([0-9a-f-]{36})/i);
  await Promise.all([
    page.waitForURL(new RegExp(`/projects/${projectUuid}/issues/[0-9a-f-]{36}`, 'i'), { timeout: 30_000 }),
    issueLink.click(),
  ]);
  await dismissProductTour(page);
  return match?.[1] ?? null;
}

/** CSS that strips Symfony/dev overlays so screenshots look production-like. */
export const MANUAL_DEV_CHROME_HIDE_CSS = `
  .sf-toolbar,
  [id^="sfwdt"],
  .sf-toolbar-clearer,
  .sf-minitoolbar,
  [id^="_twig_inspector"],
  ._twig_inspector__filter_highlight,
  vite-error-overlay,
  .flash-toast,
  .nowo-ui-toast,
  .nowo-ui-toast__container,
  .nowo-cookie-consent__preferences-bubble,
  #cookieconsent .nowo-cookie-consent__preferences-bubble {
    display: none !important;
    visibility: hidden !important;
    pointer-events: none !important;
  }
`;

/**
 * Force English UI for documentation shots (manual is English; demo users may prefer es).
 */
export async function ensureEnglishUi(page: Page): Promise<void> {
  const htmlLang = (await page.locator('html').getAttribute('lang'))?.toLowerCase() ?? '';
  if (htmlLang === 'en' || htmlLang.startsWith('en-')) {
    return;
  }

  const switcher = page.locator('.locale-switcher').first();
  if (!(await switcher.isVisible().catch(() => false))) {
    return;
  }

  const details = switcher.locator('details.locale-switcher__details').first();
  const summary = switcher.locator('summary.locale-switcher__summary').first();
  if (await details.count()) {
    const open = await details.getAttribute('open');
    if (open === null && (await summary.isVisible().catch(() => false))) {
      await summary.click({ force: true }).catch(() => undefined);
    }
  }

  const enOption = page
    .locator(
      [
        'form[action*="/account/locale/en"] button.locale-switcher__option',
        'form[action*="/locale/en"] button.locale-switcher__option',
        'a.locale-switcher__option[hreflang="en"]',
        'button.locale-switcher__option[hreflang="en"]',
        'button.locale-switcher__option[lang="en"]',
      ].join(', '),
    )
    .first();

  if (!(await enOption.isVisible().catch(() => false))) {
    return;
  }

  await Promise.all([
    page.waitForLoadState('domcontentloaded').catch(() => undefined),
    enOption.click({ force: true }),
  ]);
  await waitForPageLoader(page);
}

/**
 * Prepare the page for documentation screenshots: dismiss product UX overlays
 * and hide development chrome (WDT, Twig Inspector, Vite error overlay, toasts).
 */
export async function prepareProductionScreenshot(page: Page): Promise<void> {
  await waitForPageLoader(page);
  await dismissCookieConsent(page);
  await dismissProductTour(page);
  await ensureEnglishUi(page);
  await dismissCookieConsent(page);
  await dismissProductTour(page);
  await page.addStyleTag({ content: MANUAL_DEV_CHROME_HIDE_CSS }).catch(() => undefined);
  await page
    .evaluate(() => {
      document
        .querySelectorAll(
          '.sf-toolbar, [id^="sfwdt"], .sf-toolbar-clearer, .sf-minitoolbar, [id^="_twig_inspector"], vite-error-overlay, .flash-toast, .nowo-ui-toast, .nowo-ui-toast__container, .nowo-cookie-consent__preferences-bubble',
        )
        .forEach((el) => el.remove());
    })
    .catch(() => undefined);
  await page.waitForTimeout(150);
}

/** Fixed documentation viewport — never fullPage (keeps aside/chrome aspect ratio). */
export const MANUAL_VIEWPORT = { width: 1440, height: 900 } as const;

export type ManualTheme = 'light' | 'dark';

/**
 * Force day/night theme for screenshots (overrides user preference + localStorage).
 */
export async function setManualTheme(page: Page, theme: ManualTheme): Promise<void> {
  try {
    await page.evaluate((t) => {
      localStorage.setItem('beacon-theme', t);
      const root = document.documentElement;
      root.dataset.theme = t;
      root.dataset.userTheme = t;
      window.__BEACON_USER_THEME__ = t;
    }, theme);
  } catch {
    // about:blank / opaque origins deny localStorage — caller should navigate first.
    return;
  }
  await page.waitForTimeout(100);
}

export type CaptureManualOptions = {
  /** Default light. Dark shots are saved as `{name}-dark.png`. */
  theme?: ManualTheme;
  /** Extra same-size scrolls for tall pages (default 1 = only above-the-fold). Max 3. */
  maxParts?: number;
};

/**
 * Capture fixed-size viewport PNGs (1440×900). Tall pages get `-2` / `-3` companions
 * of the same dimensions after scrolling — never a stretched full-page image.
 */
export async function captureManualScreenshot(
  page: Page,
  outDir: string,
  name: string,
  options: CaptureManualOptions = {},
): Promise<void> {
  const theme = options.theme ?? 'light';
  const maxParts = Math.min(Math.max(options.maxParts ?? 1, 1), 3);
  await setManualTheme(page, theme);
  await prepareProductionScreenshot(page);
  // Re-apply theme after prepare (locale switch may reload).
  await setManualTheme(page, theme);
  await page.setViewportSize(MANUAL_VIEWPORT);
  fs.mkdirSync(outDir, { recursive: true });

  const baseName = theme === 'dark' ? `${name}-dark` : name;
  const viewportHeight = MANUAL_VIEWPORT.height;

  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(80);

  for (let part = 1; part <= maxParts; part++) {
    if (part > 1) {
      await page.evaluate((y) => window.scrollTo(0, y), (part - 1) * viewportHeight);
      await page.waitForTimeout(120);
    }
    const scrollHeight = await page.evaluate(() =>
      Math.max(document.documentElement.scrollHeight, document.body?.scrollHeight ?? 0),
    );
    const maxScroll = Math.max(0, scrollHeight - viewportHeight);
    const at = (part - 1) * viewportHeight;
    if (part > 1 && at > maxScroll + 40) {
      break;
    }
    const fileName = part === 1 ? `${baseName}.png` : `${baseName}-${part}.png`;
    await page.screenshot({
      path: path.join(outDir, fileName),
      fullPage: false,
      animations: 'disabled',
      caret: 'hide',
    });
  }
  await page.evaluate(() => window.scrollTo(0, 0));
}

/** Complete a SlideToConfirm widget (hidden checkbox is the submitted value) and submit the form. */
export async function completeSlideToConfirm(form: import('@playwright/test').Locator): Promise<void> {
  const slider = form.locator('nowo-slide-to-confirm, .nowo-slide-to-confirm').first();
  await expect(slider).toBeVisible({ timeout: 10_000 });
  await expect(slider).toHaveAttribute('data-slide-to-confirm-init', '1', { timeout: 10_000 });

  const thumb = slider.locator('[data-slide-to-confirm-target="thumb"], button.nowo-slide-to-confirm__thumb').first();
  const track = slider.locator('[data-slide-to-confirm-target="track"], .nowo-slide-to-confirm__track').first();
  await expect(thumb).toBeVisible({ timeout: 10_000 });
  await expect(track).toBeVisible({ timeout: 10_000 });

  const checkbox = form.locator('input.nowo-slide-to-confirm__input[type="checkbox"]');

  // Dispatch pointer events on the thumb in-page (Playwright mouse coords miss dialog transforms in CI).
  await slider.evaluate((host) => {
    const thumbEl = host.querySelector('[data-slide-to-confirm-target="thumb"]') as HTMLButtonElement | null;
    const trackEl = host.querySelector('[data-slide-to-confirm-target="track"]') as HTMLElement | null;
    if (!thumbEl || !trackEl) {
      return;
    }
    const trackRect = trackEl.getBoundingClientRect();
    const thumbRect = thumbEl.getBoundingClientRect();
    if (trackRect.width <= 0) {
      return;
    }
    const startX = thumbRect.left + thumbRect.width / 2;
    const endX = trackRect.right - thumbRect.width / 2 - 2;
    const y = thumbRect.top + thumbRect.height / 2;
    const pointerId = 1;
    const fire = (type: string, clientX: number) => {
      thumbEl.dispatchEvent(
        new PointerEvent(type, { bubbles: true, cancelable: true, clientX, clientY: y, pointerId }),
      );
    };
    fire('pointerdown', startX);
    fire('pointermove', endX);
    fire('pointerup', endX);
  });

  if (!(await checkbox.isChecked())) {
    await thumb.evaluate((el) => {
      el.dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true, cancelable: true }));
    });
  }

  if (!(await checkbox.isChecked())) {
    await slider.evaluate((host) => {
      const input = host.querySelector('input.nowo-slide-to-confirm__input[type="checkbox"]') as HTMLInputElement | null;
      if (input !== null && !input.checked) {
        input.checked = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
      host.classList.add('is-confirmed');
    });
  }

  await expect(checkbox).toBeChecked({ timeout: 10_000 });

  const autoSubmit = await slider.getAttribute('data-slide-to-confirm-submit-on-confirm-value');
  if (autoSubmit !== '1' && autoSubmit !== 'true') {
    await form.evaluate((el) => {
      if (el instanceof HTMLFormElement) {
        el.requestSubmit();
      }
    });
  }
}

/** Clear FormKit JSON textareas that ship the literal string "null" (breaks kit JsonObjectTransformer). */
export async function clearBreadcrumbKitJsonFields(form: Locator): Promise<void> {
  const areas = form.locator('textarea');
  const n = await areas.count();
  for (let i = 0; i < n; i++) {
    const area = areas.nth(i);
    const name = (await area.getAttribute('name')) ?? '';
    if (!/(Json|Params|Attributes|Keys|translations|responsive)/i.test(name)) {
      continue;
    }
    const v = (await area.inputValue()).trim();
    if (v === '' || v === 'null' || v === '[]' || v === '{}') {
      await area.fill('');
    }
  }
}

/** Open BreadcrumbKit “new collection” modal (primary UX; full-page /new is layout-fragile). */
export async function openNewBreadcrumbCollectionForm(page: Page): Promise<Locator> {
  await expectAuthenticatedPage(page, '/breadcrumb-kit-admin/collections');
  const open = page.locator('button.btn-primary.btn-bk-collection-form, button.btn-bk-collection-form.btn-primary').first();
  await expect(open).toBeVisible({ timeout: 15_000 });
  await open.click({ force: true });
  const modal = page.locator('#modal-bk-collection-form');
  await expect(modal).toBeVisible({ timeout: 15_000 });
  const form = modal.locator('form').filter({ has: page.locator('input[name*="[code]"]') }).first();
  await expect(form.locator('input[name*="[code]"]').first()).toBeVisible({ timeout: 15_000 });
  return form;
}

/**
 * Create an ephemeral breadcrumb collection (full-page /new).
 * Modal create posts `_modal=1` and on validation error returns a bare partial — that breaks
 * Playwright navigation and made CI flake when the kit form rejected the submit.
 */
export async function createEphemeralBreadcrumbCollection(
  page: Page,
  suffix: string,
): Promise<{ code: string; collectionId: string; name: string }> {
  const code = `e2e_bk_${suffix}`;
  const name = `E2E BK ${suffix}`;
  await expectAuthenticatedPage(page, '/breadcrumb-kit-admin/collections/new');
  const form = page
    .locator('form')
    .filter({ has: page.locator('input[name*="[code]"]') })
    .first();
  await expect(form.locator('input[name*="[code]"]').first()).toBeVisible({ timeout: 15_000 });
  await form.locator('input[name*="[code]"]').first().fill(code);
  const nameField = form.locator('input[name*="[name]"]');
  if ((await nameField.count()) > 0) {
    await nameField.first().fill(name);
  }
  await clearBreadcrumbKitJsonFields(form);
  // Kit redirectToRefererOr sends us back to /new (Referer), not /collections/{id}/edit.
  await form.locator('button[type="submit"]').first().click();
  await waitForPageLoader(page);
  await page.goto(`/breadcrumb-kit-admin/collections?q=${encodeURIComponent(code)}`);
  await dismissProductTour(page);
  const row = page.locator('tr').filter({ hasText: code }).first();
  await expect(row).toBeVisible({ timeout: 20_000 });
  const itemsHref = await row.locator('a[href*="/collections/"]').first().getAttribute('href');
  const collectionId = itemsHref?.match(/collections\/(\d+)/)?.[1] ?? '';
  expect(collectionId, 'breadcrumb collection id').toBeTruthy();
  return { code, collectionId, name };
}

/**
 * Open BreadcrumbKit “new item” full-page form (modal `_modal` + FormKit "null" JSON flakes CI).
 */
export async function openNewBreadcrumbItemForm(page: Page, collectionId: string): Promise<Locator> {
  await expectAuthenticatedPage(page, `/breadcrumb-kit-admin/collections/${collectionId}/items/new`);
  const form = page
    .locator('form')
    .filter({ has: page.locator('input[name="breadcrumb_item[routeName]"], input[name*="[routeName]"]') })
    .first();
  await expect(form.locator('input[name="breadcrumb_item[routeName]"], input[name*="[routeName]"]').first()).toBeVisible({
    timeout: 15_000,
  });
  await clearBreadcrumbKitJsonFields(form);
  return form;
}
