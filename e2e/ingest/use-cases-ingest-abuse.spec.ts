import { test, expect } from '@playwright/test';
import {
  beaconAuthHeader,
  dismissProductTour,
  gotoStable,
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
  await gotoStable(page, '/dashboard?new=1');
  await dismissProductTour(page);
  if (!(await page.locator('input[name="project[name]"]').isVisible().catch(() => false))) {
    await page.locator('[data-tour="new-project"], [data-action="new-project"]').first().click();
  }
  await expect(page.locator('input[name="project[name]"]')).toBeVisible({ timeout: 10_000 });
  await page.locator('input[name="project[name]"]').fill(name);
  await page.locator('textarea[name="project[description]"]').fill('Ephemeral Playwright ingest abuse');
  await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();
  await page.waitForURL(/\/projects\/([0-9a-f-]{36})/i, { timeout: 30_000 });
  await dismissProductTour(page);
  const match = page.url().match(/\/projects\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse project UUID from ${page.url()}`);
  }
  return match[1];
}

test.describe('Ingest abuse — empty, oversized, cross-project Read API', () => {
  test('empty envelope body returns 400 (UC-ING-20)', async ({ request }) => {
    const creds = requireCreds();
    const base = ingestHttpBase();
    const res = await request.post(`${base}/api/${creds.projectId}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(creds.publicKey, creds.secretKey),
      },
      data: '',
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(res.status(), await res.text()).toBe(400);
    await expect(await res.text()).toMatch(/empty body/i);
  });

  test('oversized envelope returns 413 (UC-ING-21)', async ({ request }) => {
    test.setTimeout(90_000);
    const creds = requireCreds();
    const base = ingestHttpBase();
    // Default envelopeMaxBytes is 2 MiB — send one byte over the cap.
    const res = await request.post(`${base}/api/${creds.projectId}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(creds.publicKey, creds.secretKey),
      },
      data: 'x'.repeat(2_097_153),
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(res.status(), await res.text()).toBe(413);
    await expect(await res.text()).toMatch(/too large/i);
  });

  test('Read API token cannot list another project (UC-ING-19)', async ({ page, request }) => {
    test.setTimeout(90_000);
    const demoUuid = await resolveDemoProjectUuid(page);
    const otherUuid = await createEphemeralProject(page, `E2E Read Scope ${Date.now().toString(36)}`);

    await page.goto(`/projects/${otherUuid}/settings/access`);
    await dismissProductTour(page);
    const tokens = page.locator('[data-testid="read-api-tokens"]');
    await expect(tokens).toBeVisible({ timeout: 15_000 });
    const label = `e2e-scope-${Date.now().toString(36)}`;
    await tokens.locator('input[name="project_read_token_create[label]"]').fill(label);
    await tokens.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    const secret = page.locator('[data-testid="read-token-secret"]');
    await expect(secret).toBeVisible({ timeout: 15_000 });
    const bearer = (await secret.innerText()).trim();

    const cross = await request.get(`/api/projects/${demoUuid}/issues`, {
      headers: { Authorization: `Bearer ${bearer}` },
      failOnStatusCode: false,
    });
    expect(cross.status(), await cross.text()).toBe(403);
    await expect(await cross.text()).toMatch(/forbidden/i);

    const own = await request.get(`/api/projects/${otherUuid}/issues`, {
      headers: { Authorization: `Bearer ${bearer}` },
      failOnStatusCode: false,
    });
    expect(own.status(), await own.text()).toBe(200);
  });

  test('forged Read API bearer is unauthorized (UC-ING-22)', async ({ page, request }) => {
    const uuid = await resolveDemoProjectUuid(page);
    const res = await request.get(`/api/projects/${uuid}/issues`, {
      headers: { Authorization: 'Bearer brt_definitely-not-a-real-token' },
      failOnStatusCode: false,
    });
    expect(res.status(), await res.text()).toBe(401);
    await expect(await res.text()).toMatch(/unauthorized/i);
  });
});
