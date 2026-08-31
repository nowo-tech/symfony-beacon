import { test, expect } from '@playwright/test';
import {
  beaconAuthHeader,
  createApiKeyAndParseDsn,
  dismissProductTour,
  ingestHttpBase,
  waitForPageLoader,
} from '../support/helpers';

async function createEphemeralProject(page: import('@playwright/test').Page, name: string): Promise<string> {
  await page.goto('/dashboard?new=1');
  await dismissProductTour(page);
  if (!(await page.locator('input[name="project[name]"]').isVisible().catch(() => false))) {
    await page.locator('[data-tour="new-project"], [data-action="new-project"]').first().click();
  }
  await expect(page.locator('input[name="project[name]"]')).toBeVisible({ timeout: 10_000 });
  await page.locator('input[name="project[name]"]').fill(name);
  await page.locator('textarea[name="project[description]"]').fill('Ephemeral threshold delivery E2E');
  await page.locator('dialog form button[type="submit"], form[action*="/projects/new"] button[type="submit"]').first().click();
  await page.waitForURL(/\/projects\/([0-9a-f-]{36})/i, { timeout: 30_000 });
  await dismissProductTour(page);
  const match = page.url().match(/\/projects\/([0-9a-f-]{36})/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse project UUID from ${page.url()}`);
  }
  return match[1];
}

function minimalErrorEnvelope(message: string): string {
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
      environment: 'e2e',
      message,
      exception: {
        values: [
          {
            type: 'RuntimeException',
            value: message,
            stacktrace: {
              frames: [{ filename: 'e2e/threshold-delivery.spec.ts', function: 'test', lineno: 1, in_app: true }],
            },
          },
        ],
      },
    }),
  ].join('\n');
}

async function selectDestinationCategories(
  page: import('@playwright/test').Page,
  form: ReturnType<typeof page.locator>,
  categories: string[],
): Promise<void> {
  const select = form.locator('select[name="notification_destination[categories][]"]');
  if ((await select.count()) > 0) {
    await select.selectOption(categories, { force: true }).catch(async () => {
      for (const cat of categories) {
        await select.selectOption(cat, { force: true }).catch(() => undefined);
      }
    });
    return;
  }
  const ts = form.locator('.ts-control').first();
  if (await ts.isVisible().catch(() => false)) {
    await ts.click();
    for (const cat of categories) {
      const opt = page.locator('.ts-dropdown .option').filter({ hasText: new RegExp(cat.replace('.', '\\.'), 'i') }).first();
      if ((await opt.count()) > 0) {
        await opt.click({ force: true });
      }
    }
  }
}

test.describe('Threshold delivery after ingest', () => {
  test('volume threshold fires and records a delivery attempt (UC-NOTIF-19)', async ({ page, request }) => {
    test.setTimeout(180_000);
    const suffix = Date.now().toString(36);
    const uuid = await createEphemeralProject(page, `E2E ThreshDel ${suffix}`);
    const destLabel = `e2e-thresh-dest-${suffix}`;
    const ruleLabel = `e2e-thresh-rule-${suffix}`;

    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);
    const destForm = page.getByRole('main').locator('form.notification-destination-form');
    await expect(destForm).toBeVisible({ timeout: 15_000 });
    await destForm.locator('input[name="notification_destination[label]"]').fill(destLabel);
    await destForm.locator('select[name="notification_destination[type]"]').selectOption('http');
    await destForm.locator('input[name="notification_destination[endpointUrl]"]').fill('https://example.com/hooks/beacon-threshold-e2e');
    await selectDestinationCategories(page, destForm, ['error', 'volume.threshold']);
    await destForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/alerts`), { timeout: 20_000 });
    await expect(page.locator('#project-notification-destinations')).toContainText(destLabel, { timeout: 15_000 });

    await page.goto(`/projects/${uuid}/threshold-rules/new`);
    await dismissProductTour(page);
    const ruleForm = page.getByRole('main').locator('form').first();
    await expect(ruleForm).toBeVisible();
    await ruleForm.locator('input[name="project_threshold_rule[label]"]').fill(ruleLabel);
    await ruleForm.locator('input[name="project_threshold_rule[errorCount]"]').fill('1');
    await ruleForm.locator('input[name="project_threshold_rule[windowMinutes]"]').fill('15');
    await ruleForm.locator('input[name="project_threshold_rule[cooldownMinutes]"]').fill('30');
    await ruleForm.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page.getByRole('main')).toContainText(ruleLabel, { timeout: 15_000 });

    const { publicKey, secretKey, projectRef } = await createApiKeyAndParseDsn(page, uuid, `e2e-th-key-${suffix}`);
    const base = ingestHttpBase();
    const ingest = await request.post(`${base}/api/${projectRef}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(publicKey, secretKey),
      },
      data: minimalErrorEnvelope(`threshold-trigger-${suffix}`),
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect([200, 201, 202], await ingest.text()).toContain(ingest.status());

    let thresholdFired = false;
    let deliveryRecorded = false;
    for (let i = 0; i < 45; i++) {
      await page.goto(`/projects/${uuid}/settings/alerts`);
      await dismissProductTour(page);

      const ruleRow = page.locator('li').filter({ hasText: ruleLabel }).first();
      if ((await ruleRow.count()) > 0) {
        const ruleText = await ruleRow.innerText();
        if (/last fired|último|dispar|fired at|20\d{2}/i.test(ruleText) && !/never fired|nunca/i.test(ruleText)) {
          thresholdFired = true;
        }
      }

      const health = page.locator('section.panel').filter({ hasText: /health|salud/i }).first();
      if ((await health.count()) > 0) {
        const healthText = await health.innerText();
        if (/last delivery|última entrega|delivery_ok|delivery_fail|history_show|historial/i.test(healthText)) {
          if (!/never delivered|nunca entreg/i.test(healthText)) {
            deliveryRecorded = true;
          }
        }
        const historyToggle = health.locator('details summary').first();
        if ((await historyToggle.count()) > 0) {
          await historyToggle.click().catch(() => undefined);
          if ((await health.locator('details ul li').count()) > 0) {
            deliveryRecorded = true;
          }
        }
      }

      if (thresholdFired && deliveryRecorded) {
        break;
      }
      await page.waitForTimeout(2_000);
    }

    expect(thresholdFired, 'threshold rule should record lastFiredAt after ingest').toBeTruthy();
    expect(deliveryRecorded, 'destination health should show a delivery attempt').toBeTruthy();
  });
});
