import { test, expect } from '@playwright/test';
import {
  beaconAuthHeader,
  dismissProductTour,
  ingestHttpBase,
  loadDemoIngestCredentials,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

function requireCreds() {
  const creds = loadDemoIngestCredentials();
  if (!creds) {
    requireSampleOrSkip(false, '.demo-client.env missing — run make seed');
  }
  return creds!;
}

async function createEphemeralProject(page: import('@playwright/test').Page, name: string): Promise<string> {
  await page.goto('/dashboard?new=1');
  await dismissProductTour(page);
  const dialog = page.locator('dialog[open], dialog.confirm-dialog[open], dialog:not([hidden])').first();
  if (!(await dialog.isVisible().catch(() => false))) {
    await page.locator('[data-action="new-project"]').click();
  }
  await expect(page.locator('input[name="project[name]"]')).toBeVisible({ timeout: 10_000 });
  await page.locator('input[name="project[name]"]').fill(name);
  await page.locator('textarea[name="project[description]"]').fill('Ephemeral Playwright ingest edges');
  await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();
  await page.waitForURL(/\/projects\/([0-9a-f-]{36})/i, { timeout: 30_000 });
  await dismissProductTour(page);
  const match = page.url().match(/\/projects\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse project UUID from ${page.url()}`);
  }
  return match[1];
}

function minimalEnvelope(message: string): string {
  const eventId = crypto.randomUUID().replace(/-/g, '');
  return [
    JSON.stringify({ event_id: eventId, sent_at: new Date().toISOString() }),
    JSON.stringify({ type: 'event' }),
    JSON.stringify({
      event_id: eventId,
      timestamp: Date.now() / 1000,
      platform: 'php',
      level: 'error',
      logger: 'playwright.e2e',
      server_name: 'playwright',
      transaction: 'e2e.ingest-edges',
      environment: 'e2e',
      message,
      exception: {
        values: [
          {
            type: 'RuntimeException',
            value: message,
            stacktrace: {
              frames: [{ filename: 'e2e/use-cases-ingest-edges.spec.ts', function: 'test', lineno: 1, in_app: true }],
            },
          },
        ],
      },
    }),
  ].join('\n');
}

async function createKeyAndParseDsn(
  page: import('@playwright/test').Page,
  uuid: string,
  label: string,
): Promise<{ publicKey: string; secretKey: string; projectRef: string }> {
  await page.goto(`/projects/${uuid}/settings/access`);
  await dismissProductTour(page);
  const createForm = page.locator('form').filter({ has: page.locator('input[name="project_api_key_create[label]"]') });
  await createForm.locator('input[name="project_api_key_create[label]"]').fill(label);
  await createForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
  await waitForPageLoader(page);
  const flash = page.locator('[data-testid="api-key-dsn-flash"]');
  await expect(flash).toBeVisible({ timeout: 15_000 });
  const flashText = await flash.innerText();
  const dsnMatch = flashText.match(/https?:\/\/([^:]+):([^@]+)@[^/\s]+\/([^\s]+)/i);
  expect(dsnMatch, 'DSN in flash').toBeTruthy();
  return { publicKey: dsnMatch![1], secretKey: dsnMatch![2], projectRef: dsnMatch![3] };
}

test.describe('Ingest edges — malformed, quotas, bad DSN', () => {
  test('malformed envelope body returns 400 (UC-ING-13)', async ({ request }) => {
    const creds = requireCreds();
    const base = ingestHttpBase();
    const res = await request.post(`${base}/api/${creds.projectId}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(creds.publicKey, creds.secretKey),
      },
      data: 'not-a-valid-envelope\n{broken',
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(res.status(), await res.text()).toBe(400);
    await expect(await res.text()).toMatch(/invalid envelope/i);
  });

  test('monthly quota exceeded returns 429 (UC-ING-14)', async ({ page, request }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const uuid = await createEphemeralProject(page, `E2E Monthly Quota ${suffix}`);

    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    const quota = page.locator(
      '#project_governance_event_quota_monthly, input[name="project_governance[event_quota_monthly]"]',
    );
    await expect(quota).toBeVisible({ timeout: 15_000 });
    await quota.fill('1');
    await page.locator('form').filter({ has: quota }).locator('button[type="submit"]').click();
    await waitForPageLoader(page);

    const { publicKey, secretKey, projectRef } = await createKeyAndParseDsn(page, uuid, `e2e-mq-${suffix}`);
    const base = ingestHttpBase();

    const first = await request.post(`${base}/api/${projectRef}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(publicKey, secretKey),
      },
      data: minimalEnvelope(`monthly-first-${suffix}`),
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect([200, 201, 202], await first.text()).toContain(first.status());

    let processed = false;
    for (let i = 0; i < 30; i++) {
      await page.goto(`/projects/${uuid}/issues`);
      await dismissProductTour(page);
      if ((await page.locator(`a[href*="/projects/${uuid}/issues/"]`).count()) > 0) {
        processed = true;
        break;
      }
      await page.waitForTimeout(1_000);
    }
    expect(processed, 'first envelope should appear as an issue').toBeTruthy();

    const second = await request.post(`${base}/api/${projectRef}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(publicKey, secretKey),
      },
      data: minimalEnvelope(`monthly-second-${suffix}`),
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(second.status(), await second.text()).toBe(429);
    await expect(await second.text()).toMatch(/quota/i);
  });

  test('per-project ingest rate limit returns 429 (UC-ING-15)', async ({ page, request }) => {
    const suffix = Date.now().toString(36);
    const uuid = await createEphemeralProject(page, `E2E Rate Limit ${suffix}`);

    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    const rate = page.locator(
      '#project_governance_ingest_rate_limit_per_minute, input[name="project_governance[ingest_rate_limit_per_minute]"]',
    );
    await expect(rate).toBeVisible({ timeout: 15_000 });
    await rate.fill('1');
    await page.locator('form').filter({ has: rate }).locator('button[type="submit"]').click();
    await waitForPageLoader(page);

    const { publicKey, secretKey, projectRef } = await createKeyAndParseDsn(page, uuid, `e2e-rl-${suffix}`);
    const base = ingestHttpBase();
    const headers = {
      'Content-Type': 'application/x-beacon-envelope',
      'X-Beacon-Auth': beaconAuthHeader(publicKey, secretKey),
    };

    const first = await request.post(`${base}/api/${projectRef}/envelope/`, {
      headers,
      data: minimalEnvelope(`rate-1-${suffix}`),
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect([200, 201, 202, 429], await first.text()).toContain(first.status());

    const second = await request.post(`${base}/api/${projectRef}/envelope/`, {
      headers,
      data: minimalEnvelope(`rate-2-${suffix}`),
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(second.status(), await second.text()).toBe(429);
    await expect(await second.text()).toMatch(/rate limit/i);
  });

  test('unknown project / bad secret returns 401 or 404 (UC-ING-16)', async ({ request }) => {
    const creds = requireCreds();
    const base = ingestHttpBase();

    const badSecret = await request.post(`${base}/api/${creds.projectId}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(creds.publicKey, 'definitely-not-the-secret'),
      },
      data: minimalEnvelope('bad-secret'),
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect([401, 403], await badSecret.text()).toContain(badSecret.status());

    const unknown = await request.post(`${base}/api/00000000-0000-4000-8000-000000000000/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(creds.publicKey, creds.secretKey),
      },
      data: minimalEnvelope('unknown-project'),
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect([401, 404], await unknown.text()).toContain(unknown.status());
  });

  test('Read API without Bearer is unauthorized (UC-ING-18)', async ({ page, request }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const res = await request.get(`/api/projects/${uuid}/issues`, { failOnStatusCode: false });
    expect(res.status(), await res.text()).toBeGreaterThanOrEqual(401);
    expect(res.status()).toBeLessThan(500);
  });
});
