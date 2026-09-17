import type { Page } from '@playwright/test';

/** Result of the documentation screenshot page gate (REQ-DOCS-APP-003). */
export type ManualPageGateResult = {
  /** When true, write the PNG (including application error pages). */
  ok: boolean;
  /**
   * `error-page` = Symfony/HTTP 500 (or similar) — still captured on purpose so
   * operators can review broken UI in docs/manual PNGs.
   */
  kind?: 'ok' | 'error-page';
  reason?: string;
};

export type ManualPageGateOptions = {
  /** Accept styled auth/password gates (not bare-text 403). */
  allowAuthGate?: boolean;
  /**
   * Requested path for this shot. Rejects unexpected redirects
   * (e.g. /register → /login, /login/qr → /login).
   * Not applied when the landed page is an application error (500) — those
   * are captured for code review even if the path drifted.
   */
  expectedPath?: string;
};

const MANUAL_LOCALE_PREFIX = /^\/(en|es|ca|fr|de|it|pt)(?=\/|$)/i;

/** Strip trailing slash + optional public locale prefix for path compares. */
export function normalizeManualPathname(pathname: string): string {
  let p = pathname.replace(/\/+$/, '') || '/';
  p = p.replace(MANUAL_LOCALE_PREFIX, '') || '/';
  return p;
}

/**
 * True when the landed URL still represents the requested shot path.
 * Allows trailing slash, locale prefix, and Identity create-in-modal
 * redirects (`/…/new` or `?new=1` → list with modal).
 */
export function manualPathMatchesExpected(actualUrl: string, expectedPath: string): boolean {
  let actual: URL;
  let expected: URL;
  try {
    actual = new URL(actualUrl);
    expected = expectedPath.startsWith('http')
      ? new URL(expectedPath)
      : new URL(expectedPath, actual.origin);
  } catch {
    return false;
  }

  const aPath = normalizeManualPathname(actual.pathname);
  const ePath = normalizeManualPathname(expected.pathname);

  if (aPath === ePath) {
    return true;
  }

  if (ePath.startsWith('/_error') || ePath === '/_maintenance_preview') {
    if (aPath === ePath || aPath.startsWith(`${ePath}/`)) {
      return true;
    }
  }

  // Create-in-modal / legacy /new → parent list.
  if (/\/new$/.test(ePath) || expected.searchParams.get('new') === '1') {
    const listPath = ePath.replace(/\/new$/, '') || '/';
    if (aPath === listPath) {
      return true;
    }
  }

  // Setup progress / done may keep query tokens.
  if (ePath.startsWith('/setup') || ePath.startsWith('/_setup')) {
    if (aPath === ePath || aPath.startsWith(`${ePath}/`)) {
      return true;
    }
  }

  // CookieConsent admin entry often redirects to kit config UI.
  if (
    ePath === '/admin/cookie-consent' &&
    (aPath.startsWith('/cookie-consent-config') ||
      aPath.startsWith('/admin/cookie-consent-config') ||
      aPath.startsWith('/cookie-consent/'))
  ) {
    return true;
  }

  // Appearance settings land on the first section (usually /themes).
  if (
    (ePath === '/settings/appearance' || ePath === '/admin/settings/appearance') &&
    (aPath === ePath || aPath.startsWith(`${ePath}/`))
  ) {
    return true;
  }

  return false;
}

type PageReport = {
  code: 200 | 400 | 401 | 403 | 404 | 408 | 429 | 500 | 502 | 503;
  bare: boolean;
  branded?: boolean;
};

async function classifyManualPage(page: Page): Promise<PageReport> {
  return page.evaluate(() => {
    const title = document.title.toLowerCase();
    const rawBody = document.body?.innerText?.replace(/\s+/g, ' ').trim() ?? '';
    const body = rawBody.slice(0, 1200).toLowerCase();
    // Symfony Whoops / exception pages only — never match product copy that
    // mentions exceptions/errors (Beacon issue detail, Ops "Error spikes", etc.).
    const hasExceptionDom = Boolean(
      document.querySelector('.exception-summary, .sf-exception, .exception-message-wrapper'),
    );
    const looksLikeSymfony500 =
      hasExceptionDom ||
      title.includes('symfony exception') ||
      body.includes('unable to find template') ||
      body.includes('twig\\error\\loadererror') ||
      body.includes('http 500 internal server error') ||
      body.includes('an exception has been thrown during the rendering of a template');

    if (looksLikeSymfony500) {
      return { code: 500 as const, bare: false };
    }
    const branded =
      document.body?.classList.contains('error-page') ||
      Boolean(
        document.querySelector('[data-error-status], .error-page__img, .error-page__figure'),
      );
    if (branded) {
      const attrCode = Number.parseInt(
        document.querySelector('[data-error-status]')?.getAttribute('data-error-status') ?? '',
        10,
      );
      const titleCode = Number.parseInt(
        title.match(/\b(400|401|403|404|408|429|500|502|503)\b/)?.[1] ?? '',
        10,
      );
      const code = ([attrCode, titleCode].find((value) =>
        [400, 401, 403, 404, 408, 429, 500, 502, 503].includes(value),
      ) ?? 500) as PageReport['code'];

      return { code, bare: false, branded: true };
    }
    if (title.includes('404') || body.includes('page not found') || body.includes('no route found')) {
      return { code: 404 as const, bare: false };
    }
    if (
      rawBody === 'Access Denied.' ||
      rawBody === 'Access Denied' ||
      body === 'access denied.' ||
      body === 'access denied'
    ) {
      return { code: 403 as const, bare: true };
    }
    if (title.includes('403') || body.includes('access denied') || body.includes('forbidden')) {
      return { code: 403 as const, bare: false };
    }
    return { code: 200 as const, bare: false };
  });
}

/**
 * Gate before writing docs/manual PNGs.
 *
 * - **Captures** application error pages (Symfony 500 / Whoops / LoaderError)
 *   so broken UI is visible in the manual for code review (`kind: 'error-page'`).
 * - **Rejects** browser chrome-errors, bare 403/404, and unexpected redirects
 *   that look like a healthy wrong page (e.g. /register → Sign in).
 */
export async function inspectManualPage(
  page: Page,
  options: ManualPageGateOptions = {},
): Promise<ManualPageGateResult> {
  const url = page.url();
  if (url.startsWith('about:') || url.startsWith('chrome-error:')) {
    return { ok: false, reason: `browser error url ${url}` };
  }

  const report = await classifyManualPage(page);

  // Keep 500 PNGs — they are the review artifact when the stack is broken.
  if (report.code === 500 && !report.branded) {
    return {
      ok: true,
      kind: 'error-page',
      reason: `application error page at ${url}`,
    };
  }

  if (options.expectedPath && !manualPathMatchesExpected(url, options.expectedPath)) {
    return {
      ok: false,
      reason: `unexpected redirect: wanted ${options.expectedPath}, landed ${url}`,
    };
  }

  if (report.branded) {
    return { ok: true, kind: 'ok' };
  }

  if (report.code === 404) {
    return { ok: false, reason: `not found at ${url}` };
  }
  if (report.code === 403) {
    if (report.bare) {
      return { ok: false, reason: `bare access denied at ${url}` };
    }
    if (!options.allowAuthGate) {
      return { ok: false, reason: `forbidden at ${url}` };
    }
  }

  // Reject near-empty shells (e.g. white flash before setup/token UI paints).
  const bodyText = await page
    .locator('body')
    .innerText()
    .then((t) => t.replace(/\s+/g, ' ').trim())
    .catch(() => '');
  if (bodyText.length < 40) {
    return { ok: false, reason: `blank or empty page at ${url} (${bodyText.length} chars)` };
  }

  return { ok: true, kind: 'ok' };
}

/** Boolean wrapper for older call sites (true for healthy and error-page captures). */
export async function manualPageLooksOk(
  page: Page,
  options: ManualPageGateOptions = {},
): Promise<boolean> {
  return (await inspectManualPage(page, options)).ok;
}

/**
 * AuthKit guest shots must show the intended surface — not a silent redirect to Sign in.
 * Application 500s are still captured (`kind: 'error-page'`).
 */
export async function inspectManualAuthShot(
  page: Page,
  shotPath: string,
): Promise<ManualPageGateResult> {
  const base = await inspectManualPage(page, { expectedPath: shotPath });
  if (!base.ok || base.kind === 'error-page') {
    return base;
  }

  const pathNorm = normalizeManualPathname(new URL(page.url()).pathname);
  const isQr = /\/login\/qr$/.test(pathNorm) || /\/qr$/.test(pathNorm);
  if (isQr) {
    const qrOk = await page
      .locator(
        '.auth-qr-challenge, .nowo-auth-kit__qr-challenge, .auth-qr-challenge__image, .auth-qr-challenge__code',
      )
      .first()
      .isVisible()
      .catch(() => false);
    const rateLimited = await page
      .locator('body')
      .innerText()
      .then((t) => /too many requests/i.test(t))
      .catch(() => false);
    if (!qrOk || rateLimited) {
      return {
        ok: false,
        reason: `QR challenge not visible at ${page.url()}`,
      };
    }
    return { ok: true, kind: 'ok' };
  }

  const formVisible = await page
    .locator(
      'form[name="login_form"], form[name="registration_form"], form[name="reset_password_request_form"], form[name="magic_login_form"], form.nowo-auth-kit__form, .nowo-auth-kit__panel form, input[type="password"], input[type="email"]',
    )
    .first()
    .isVisible()
    .catch(() => false);
  if (!formVisible) {
    return { ok: false, reason: `auth form not visible at ${page.url()}` };
  }

  if (/\/register$/.test(pathNorm)) {
    const registerHint = await page
      .locator(
        'form[name="registration_form"], input[name*="plainPassword"], input[name*="[email]"], button:has-text("Register"), button:has-text("Create account")',
      )
      .first()
      .isVisible()
      .catch(() => false);
    const signInOnly = await page
      .locator('h1, h2, .nowo-auth-kit__title, legend')
      .filter({ hasText: /^sign in$/i })
      .first()
      .isVisible()
      .catch(() => false);
    if (signInOnly && !registerHint) {
      return { ok: false, reason: `register shot landed on Sign in at ${page.url()}` };
    }
  }

  if (/\/login\/magic$/.test(pathNorm) || /\/magic-login/.test(pathNorm)) {
    const magicHint = await page
      .locator(
        'form[name="magic_login_form"], form[name="magic_login_request_form"], input[name*="magic"], button:has-text("Email me a link"), button:has-text("Send magic link"), button:has-text("Send link")',
      )
      .first()
      .isVisible()
      .catch(() => false);
    const passwordField = await page
      .locator('form[name="login_form"] input[type="password"], input[name="login_form[_password]"]')
      .first()
      .isVisible()
      .catch(() => false);
    if (!magicHint || passwordField) {
      return { ok: false, reason: `magic-link shot is not magic form at ${page.url()}` };
    }
  }

  if (/\/reset-password$/.test(pathNorm)) {
    const resetHint = await page
      .locator(
        'form[name="reset_password_request_form"], form[name="reset_password_form"], button:has-text("Reset"), button:has-text("Send reset")',
      )
      .first()
      .isVisible()
      .catch(() => false);
    const passwordField = await page
      .locator('form[name="login_form"] input[type="password"], input[name="login_form[_password]"]')
      .first()
      .isVisible()
      .catch(() => false);
    if (!resetHint || passwordField) {
      return { ok: false, reason: `reset-password shot is not reset form at ${page.url()}` };
    }
  }

  return { ok: true, kind: 'ok' };
}
