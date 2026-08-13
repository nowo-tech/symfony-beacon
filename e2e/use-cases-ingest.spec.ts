import { test, expect } from '@playwright/test';
import {
  beaconAuthHeader,
  dismissProductTour,
  ingestHttpBase,
  loadDemoIngestCredentials,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

function requireCreds() {
  const creds = loadDemoIngestCredentials();
  if (!creds) {
    requireSampleOrSkip(false, '.demo-client.env missing — run make seed');
  }
  return creds!;
}

test.describe('Ingest & Read API — use cases', () => {
  test('envelope rejects query-string auth with 401 (UC-ING-02)', async ({ request }) => {
    const creds = requireCreds();
    const base = ingestHttpBase();
    const res = await request.post(
      `${base}/api/${creds.projectId}/envelope/?beacon_key=${encodeURIComponent(creds.publicKey)}&beacon_secret=${encodeURIComponent(creds.secretKey)}`,
      {
        headers: { 'Content-Type': 'application/x-beacon-envelope' },
        data: '{}\n{}\n{}',
        failOnStatusCode: false,
        ignoreHTTPSErrors: true,
      },
    );
    expect(res.status(), await res.text()).toBe(401);
  });

  test('envelope rejects missing secret with 401 (UC-ING-03)', async ({ request }) => {
    const creds = requireCreds();
    const base = ingestHttpBase();
    const res = await request.post(`${base}/api/${creds.projectId}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': `Beacon beacon_key=${creds.publicKey}`,
      },
      data: '{}\n{}\n{}',
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(res.status(), await res.text()).toBe(401);
  });

  test('OTLP logs WARN+ ACK (UC-ING-04)', async ({ request }) => {
    const creds = requireCreds();
    const base = ingestHttpBase();
    const body = {
      resourceLogs: [
        {
          scopeLogs: [
            {
              logRecords: [
                {
                  timeUnixNano: String(Date.now() * 1_000_000),
                  severityNumber: 17,
                  severityText: 'ERROR',
                  body: { stringValue: 'Playwright OTLP logs e2e' },
                  attributes: [{ key: 'service.name', value: { stringValue: 'playwright-e2e' } }],
                },
              ],
            },
          ],
        },
      ],
    };
    const res = await request.post(`${base}/api/${creds.projectId}/otlp/v1/logs`, {
      headers: {
        'Content-Type': 'application/json',
        'X-Beacon-Auth': beaconAuthHeader(creds.publicKey, creds.secretKey),
      },
      data: body,
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    expect([200, 201, 202, 204]).toContain(res.status());
  });

  test('OTLP traces ERROR span ACK (UC-ING-05)', async ({ request }) => {
    const creds = requireCreds();
    const base = ingestHttpBase();
    const body = {
      resourceSpans: [
        {
          scopeSpans: [
            {
              spans: [
                {
                  traceId: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                  spanId: 'bbbbbbbbbbbbbbbb',
                  name: 'playwright.otlp.trace',
                  startTimeUnixNano: String(Date.now() * 1_000_000),
                  endTimeUnixNano: String((Date.now() + 10) * 1_000_000),
                  status: { code: 2, message: 'error' },
                  attributes: [{ key: 'exception.type', value: { stringValue: 'RuntimeException' } }],
                },
              ],
            },
          ],
        },
      ],
    };
    const res = await request.post(`${base}/api/${creds.projectId}/otlp/v1/traces`, {
      headers: {
        'Content-Type': 'application/json',
        'X-Beacon-Auth': beaconAuthHeader(creds.publicKey, creds.secretKey),
      },
      data: body,
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    expect([200, 201, 202, 204]).toContain(res.status());
  });

  test('OTLP metrics failure-like point ACK (UC-ING-06)', async ({ request }) => {
    const creds = requireCreds();
    const base = ingestHttpBase();
    const body = {
      resourceMetrics: [
        {
          scopeMetrics: [
            {
              metrics: [
                {
                  name: 'http.server.errors',
                  sum: {
                    dataPoints: [
                      {
                        asInt: '1',
                        timeUnixNano: String(Date.now() * 1_000_000),
                        attributes: [{ key: 'error.type', value: { stringValue: 'timeout' } }],
                      },
                    ],
                  },
                },
              ],
            },
          ],
        },
      ],
    };
    const res = await request.post(`${base}/api/${creds.projectId}/otlp/v1/metrics`, {
      headers: {
        'Content-Type': 'application/json',
        'X-Beacon-Auth': beaconAuthHeader(creds.publicKey, creds.secretKey),
      },
      data: body,
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    expect([200, 201, 202, 204]).toContain(res.status());
  });

  test('OTLP rejects query-string auth (UC-ING-07)', async ({ request }) => {
    const creds = requireCreds();
    const base = ingestHttpBase();
    const res = await request.post(
      `${base}/api/${creds.projectId}/otlp/v1/logs?beacon_key=${encodeURIComponent(creds.publicKey)}&beacon_secret=${encodeURIComponent(creds.secretKey)}`,
      {
        headers: { 'Content-Type': 'application/json' },
        data: { resourceLogs: [] },
        failOnStatusCode: false,
        ignoreHTTPSErrors: true,
      },
    );
    expect(res.status(), await res.text()).toBe(401);
  });

  test('read API get single issue when list has rows (UC-ING-10)', async ({ page, request }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);

    const tokens = page.locator('[data-testid="read-api-tokens"]');
    await expect(tokens).toBeVisible();
    const label = `e2e-show-${Date.now().toString(36)}`;
    await tokens.locator('input[name="project_read_token_create[label]"]').fill(label);
    await tokens.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);

    const secret = page.locator('[data-testid="read-token-secret"]');
    await expect(secret).toBeVisible({ timeout: 15_000 });
    const bearer = (await secret.innerText()).trim();

    const list = await request.get(`/api/projects/${uuid}/issues`, {
      headers: { Authorization: `Bearer ${bearer}` },
      failOnStatusCode: false,
    });
    expect(list.status(), await list.text()).toBe(200);
    const payload = await list.json();
    const items = Array.isArray(payload)
      ? payload
      : (payload.items ?? payload.data ?? payload.issues ?? []);
    if (!Array.isArray(items) || items.length === 0) {
      // List endpoint may return a wrapper with zero rows without sample seed.
      requireSampleOrSkip(false, 'Read API list empty — run make seed-sample');
      return;
    }
    const issueUuid = items[0].uuid ?? items[0].id ?? items[0].issueUuid;
    expect(issueUuid, 'issue uuid in list').toBeTruthy();
    const show = await request.get(`/api/projects/${uuid}/issues/${issueUuid}`, {
      headers: { Authorization: `Bearer ${bearer}` },
      failOnStatusCode: false,
    });
    expect(show.status(), await show.text()).toBe(200);
  });
});
