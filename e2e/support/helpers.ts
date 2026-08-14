import { expect, test, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const DEMO_EMAIL = process.env.PLAYWRIGHT_DEMO_EMAIL ?? 'admin@symfony-beacon.local';
export const DEMO_PASSWORD = process.env.PLAYWRIGHT_DEMO_PASSWORD ?? 'admin123';

const helpersDir = path.dirname(fileURLToPath(import.meta.url));

/** Demo ingest credentials from `make seed` (`.demo-client.env`). */
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
  const envCandidates = [
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

/** Preferred HTTP ingest base (Docker clients use :9084). */
export function ingestHttpBase(): string {
  return process.env.PLAYWRIGHT_INGEST_BASE_URL ?? 'http://localhost:9084';
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

/** Navigate with retries for transient WSL/Docker net::ERR_NETWORK_CHANGED. */
export async function gotoStable(page: Page, path: string, attempts = 3): Promise<void> {
  for (let attempt = 0; attempt < attempts; attempt++) {
    try {
      await page.goto(path);
      return;
    } catch (err) {
      if (attempt === attempts - 1 || !/ERR_NETWORK_CHANGED|net::ERR_/i.test(String(err))) {
        throw err;
      }
      await page.waitForTimeout(500);
    }
  }
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

export async function loginAsDemo(page: Page, email = DEMO_EMAIL, password = DEMO_PASSWORD): Promise<void> {
  await page.goto('/login');
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
    for (let attempt = 0; attempt < 3; attempt++) {
      try {
        await page.goto('/dashboard');
        break;
      } catch (err) {
        if (attempt === 2 || !/ERR_NETWORK_CHANGED|net::ERR_/i.test(String(err))) {
          throw err;
        }
        await page.waitForTimeout(500);
      }
    }
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
  await expect(page, `Expected auth for ${path}`).not.toHaveURL(/\/login(\?|$|\/)/);
  await expect(page.locator('body')).toBeVisible();
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
  await logoutLink.click();
  await page.waitForURL(/\/login(\?|$|\/)/, { timeout: 20_000 });
}

/** Open the first issue detail for a project; returns issue UUID or null. */
export async function openFirstIssue(page: Page, projectUuid: string): Promise<string | null> {
  await page.goto(`/projects/${projectUuid}/issues`);
  await dismissProductTour(page);
  const issueLink = page.locator(`a[href*="/projects/${projectUuid}/issues/"]`).first();
  if ((await issueLink.count()) === 0) {
    return null;
  }
  const href = await issueLink.getAttribute('href');
  const match = href?.match(/\/issues\/([0-9a-f-]{36})/i);
  await issueLink.click();
  await page.waitForURL(new RegExp(`/projects/${projectUuid}/issues/[0-9a-f-]{36}`, 'i'));
  await dismissProductTour(page);
  return match?.[1] ?? null;
}
