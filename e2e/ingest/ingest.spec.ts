import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { requireSampleOrSkip, resolveDemoProjectUuid } from '../support/helpers';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Optional Envelope ingest smoke when `.demo-client.env` exists (written by `make seed`).
 * Skips cleanly when secrets are missing.
 */
test.describe('Ingest API smoke', () => {
  test('POST envelope with demo DSN when configured', async ({ request, page }) => {
    const envCandidates = [
      path.join(__dirname, '..', '.demo-client.env'),
      path.join(__dirname, '.demo-client.env.cache'),
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
      requireSampleOrSkip(false, '.demo-client.env missing/unreadable — run make seed');
      return;
    }

    const dsnMatch = envText.match(/^BEACON_DSN=(.+)$/m) ?? envText.match(/^BEACON_UI_DSN=(.+)$/m);
    const publicKeyMatch = envText.match(/^BEACON_PUBLIC_KEY=(.+)$/m);
    const projectIdMatch = envText.match(/^BEACON_PROJECT_ID=(\d+)$/m);

    let projectId = projectIdMatch?.[1];
    let publicKey = publicKeyMatch?.[1]?.trim();
    let secretKey: string | undefined;

    if (dsnMatch?.[1]) {
      const raw = dsnMatch[1].trim().replace(/^["']|["']$/g, '');
      try {
        const url = new URL(raw.replace(/^beacon:/i, 'http:'));
        publicKey = publicKey ?? decodeURIComponent(url.username);
        secretKey = decodeURIComponent(url.password);
        const id = url.pathname.replace(/^\//, '').split('/')[0];
        if (/^\d+$/.test(id)) {
          projectId = projectId ?? id;
        }
      } catch {
        const parsed = raw.match(/^(?:beacon|http|https):\/\/([^:]+):([^@]+)@[^/]+\/(\d+)/i);
        if (parsed) {
          publicKey = publicKey ?? parsed[1];
          secretKey = parsed[2];
          projectId = projectId ?? parsed[3];
        }
      }
    }

    if (!projectId || !publicKey || !secretKey) {
      requireSampleOrSkip(false, 'Could not parse demo ingest credentials from .demo-client.env');
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

    // Prefer HTTP cleartext ingest port used by Docker clients; fall back to HTTPS base.
    const httpBase = process.env.PLAYWRIGHT_INGEST_BASE_URL ?? 'http://localhost:9084';
    const authHeader = `Beacon beacon_key=${publicKey}, beacon_secret=${secretKey}`;

    const response = await request.post(`${httpBase}/api/${projectId}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': authHeader,
      },
      data: body,
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });

    expect(response.status(), await response.text()).toBeLessThan(500);
    expect([200, 201, 202, 204]).toContain(response.status());

    // UI still reachable after ingest attempt
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/issues`);
    await expect(page).not.toHaveURL(/\/login/);
  });
});
