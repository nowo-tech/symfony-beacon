import { test, expect } from '@playwright/test';
import {
  beaconAuthHeader,
  ingestHttpBase,
  loadDemoIngestCredentials,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
} from '../support/helpers';

/**
 * Optional Envelope ingest smoke when `.demo-client.env` exists (written by `make seed`).
 * Skips cleanly when secrets are missing (unless CI / PLAYWRIGHT_REQUIRE_SAMPLE=1).
 */
test.describe('Ingest API smoke', () => {
  test('POST envelope with demo DSN when configured', async ({ request, page }) => {
    const creds = loadDemoIngestCredentials();
    if (!creds) {
      requireSampleOrSkip(false, '.demo-client.env missing/unreadable — run make seed');
      return;
    }

    const eventId = crypto.randomUUID().replace(/-/g, '');
    const body = [
      JSON.stringify({ event_id: eventId, sent_at: new Date().toISOString() }),
      JSON.stringify({ type: 'event' }),
      JSON.stringify({
        event_id: eventId,
        timestamp: Date.now() / 1000,
        platform: 'php',
        level: 'error',
        logger: 'playwright.e2e',
        server_name: 'playwright',
        transaction: 'e2e.smoke',
        environment: 'e2e',
        message: 'Playwright E2E ingest smoke',
        exception: {
          values: [
            {
              type: 'RuntimeException',
              value: 'Playwright E2E ingest smoke',
              stacktrace: {
                frames: [{ filename: 'e2e/ingest.spec.ts', function: 'test', lineno: 1, in_app: true }],
              },
            },
          ],
        },
      }),
    ].join('\n');

    const response = await request.post(`${ingestHttpBase()}/api/${creds.projectId}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(creds.publicKey, creds.secretKey),
      },
      data: body,
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });

    expect(response.status(), await response.text()).toBeLessThan(500);
    expect([200, 201, 202, 204]).toContain(response.status());

    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/issues`);
    await expect(page).not.toHaveURL(/\/login/);
  });
});
