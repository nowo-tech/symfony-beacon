import { test, expect } from '@playwright/test';
import {
  beaconAuthHeader,
  dismissProductTour,
  ingestHttpBase,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from './helpers';

test.describe('Notifications & API keys — use cases', () => {
  test('creates HTTP notification destination then toggles and deletes (UC-NOTIF-02/03)', async ({ page }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/notifications/new`);
    await dismissProductTour(page);

    const form = page.getByRole('main').locator('form.notification-destination-form');
    await expect(form).toBeVisible();

    const label = `e2e-http-${Date.now().toString(36)}`;
    await form.locator('input[name="notification_destination[label]"]').fill(label);
    await form.locator('select[name="notification_destination[type]"]').selectOption('http');
    await form.locator('input[name="notification_destination[endpointUrl]"]').fill('https://example.com/hooks/beacon-e2e');

    // Categories use Tom Select — drive the underlying <select> when possible.
    const categories = form.locator('select[name="notification_destination[categories][]"]');
    if ((await categories.count()) > 0) {
      await categories.selectOption(['error'], { force: true }).catch(async () => {
        // Fallback: open Tom Select and pick first option.
        const ts = form.locator('.ts-control').first();
        if (await ts.isVisible().catch(() => false)) {
          await ts.click();
          await page.locator('.ts-dropdown .option').filter({ hasText: /error/i }).first().click({ force: true });
        }
      });
    }

    await form.locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(new RegExp(`/projects/${uuid}/settings/alerts`));
    await expect(page.getByRole('main')).toContainText(label, { timeout: 15_000 });

    const row = page.locator('li, .panel, tr').filter({ hasText: label }).first();
    const toggle = row.locator('form[action*="/toggle"] button[type="submit"]').first();
    if (await toggle.isVisible().catch(() => false)) {
      await toggle.click();
      await waitForPageLoader(page);
      await expect(page).not.toHaveURL(/\/login/);
    }

    const del = row.locator('form[action*="/delete"] button[type="submit"]').first();
    if (await del.isVisible().catch(() => false)) {
      page.once('dialog', (d) => d.accept().catch(() => undefined));
      await del.click({ force: true });
      await waitForPageLoader(page);
      await expect(page).not.toHaveURL(/\/login/);
    }
  });

  test('creates ephemeral API key, rotates it, old secret rejected (UC-PROJ-05)', async ({ page, request }) => {
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/settings/access`);
    await dismissProductTour(page);

    const label = `e2e-key-${Date.now().toString(36)}`;
    const createForm = page.locator('form').filter({ has: page.locator('input[name="project_api_key_create[label]"]') });
    await expect(createForm).toBeVisible();
    await createForm.locator('input[name="project_api_key_create[label]"]').fill(label);
    await createForm.locator('button[type="submit"].btn-primary, button.btn-primary[type="submit"]').click();
    await waitForPageLoader(page);

    const flash = page.locator('[data-testid="api-key-dsn-flash"]');
    await expect(flash).toBeVisible({ timeout: 15_000 });
    const flashText = await flash.innerText();
    const dsnMatch = flashText.match(/https?:\/\/([^:]+):([^@]+)@[^/\s]+\/([^\s]+)/i);
    expect(dsnMatch, 'DSN in flash').toBeTruthy();
    const publicKey = dsnMatch![1];
    const secretBefore = dsnMatch![2];
    const projectRef = dsnMatch![3];

    const row = page.locator('li').filter({ hasText: label }).first();
    await expect(row).toBeVisible();
    await row.locator('form[action*="/rotate"] button[type="submit"]').click();
    await waitForPageLoader(page);
    // Rotate should invalidate the previous secret even if the one-time DSN flash is raced away.
    await expect(page.locator('li').filter({ hasText: label }).first()).toBeVisible();

    const base = ingestHttpBase();
    const denied = await request.post(`${base}/api/${projectRef}/envelope/`, {
      headers: {
        'Content-Type': 'application/x-beacon-envelope',
        'X-Beacon-Auth': beaconAuthHeader(publicKey, secretBefore),
      },
      data: '{}\n{}\n{}',
      failOnStatusCode: false,
      ignoreHTTPSErrors: true,
    });
    expect(denied.status(), await denied.text()).toBe(401);

    // Best-effort cleanup (confirm-dialog revoke may stay closed — leave key inactive via rotate is enough).
    const rowAfter = page.locator('li').filter({ hasText: label }).first();
    const revokeBtn = rowAfter.locator('form[action*="/revoke"] button[type="submit"]');
    if ((await revokeBtn.count()) > 0 && (await revokeBtn.isVisible().catch(() => false))) {
      page.once('dialog', (d) => d.accept().catch(() => undefined));
      await revokeBtn.click({ force: true }).catch(() => undefined);
      await waitForPageLoader(page);
    }
  });
});
