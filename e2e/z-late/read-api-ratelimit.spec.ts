import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

/**
 * UC-ING-11 — Read API rate limit is IP-keyed (BEACON_READ_API_RATE_LIMIT, default 120/min).
 * Lives under `z-late/` so it runs after other Read API specs; cool down after 429.
 */
test.describe('Read API rate limit (UC-ING-11)', () => {
  test('exceeding BEACON_READ_API_RATE_LIMIT returns 429', async ({ page, request }) => {
    test.setTimeout(180_000);

    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);

    const tokens = page.locator('[data-testid="read-api-tokens"]');
    await expect(tokens).toBeVisible();
    const label = `e2e-ratelimit-${Date.now().toString(36)}`;
    await tokens.locator('input[name="project_read_token_create[label]"]').fill(label);
    await tokens.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);

    const secret = page.locator('[data-testid="read-token-secret"]');
    await expect(secret).toBeVisible({ timeout: 15_000 });
    const bearer = (await secret.innerText()).trim();
    expect(bearer.startsWith('brt_')).toBeTruthy();

    const headers = { Authorization: `Bearer ${bearer}` };
    let saw429 = false;
    let accepted = 0;

    // Default limit is 120/min — burn in parallel batches, stop early once throttled.
    for (let round = 0; round < 8 && !saw429; round++) {
      const batch = await Promise.all(
        Array.from({ length: 30 }, () =>
          request.get(`/api/projects/${uuid}/issues`, {
            headers,
            failOnStatusCode: false,
          }),
        ),
      );
      for (const res of batch) {
        const status = res.status();
        if (status === 429) {
          saw429 = true;
          const body = await res.text();
          expect(body).toMatch(/rate_limit/i);
          expect(res.headers()['retry-after'] ?? res.headers()['Retry-After']).toBeTruthy();
        } else if (status === 200) {
          accepted += 1;
        }
      }
    }

    expect(saw429, `expected 429 after ~120 accepted (saw ${accepted}×200)`).toBeTruthy();

    // Sliding window is 1 minute — wait so later suites (or re-runs) are not poisoned.
    await page.waitForTimeout(65_000);
  });
});
