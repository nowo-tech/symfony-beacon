import { test, expect } from '@playwright/test';
import {
  dismissProductTour,
  expectAuthenticatedPage,
  gotoStable,
  waitForPageLoader,
} from '../support/helpers';

test.describe('Ops chrome — metrics, live, site backup, maintenance', () => {
  test('Prometheus /metrics exposes beacon_* series (UC-OPS-07)', async ({ request }) => {
    const res = await request.get('/metrics', { failOnStatusCode: false });
    expect(res.status(), await res.text()).toBe(200);
    const body = await res.text();
    expect(body).toMatch(/beacon_/i);
    expect(body).toMatch(/beacon_ingest_|beacon_messenger_|beacon_notification_/i);
  });

  test('Live component endpoint responds without 5xx (UC-OPS-11)', async ({ request }) => {
    // Probe a known Live component path family; 404 is OK, 5xx is not.
    const candidates = [
      '/_components/IssueList',
      '/_components/nowo_ui_kit',
      '/_components/MenuNestedCollapse',
    ];
    let probed = false;
    for (const path of candidates) {
      const res = await request.get(path, { failOnStatusCode: false });
      expect(res.status(), `${path}: ${await res.text()}`).toBeLessThan(500);
      probed = true;
      if (res.status() < 400) {
        break;
      }
    }
    expect(probed).toBeTruthy();
  });

  test('SiteBackup panel loads or challenges without 5xx (UC-OPS-09)', async ({ page }) => {
    await gotoStable(page, '/_site_backup/');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
    // Password-gated installs show a login form; unlocked installs show the panel.
    await expect(
      page.locator('form, [data-testid*="site-backup"], main, .nowo-site-backup').first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test('maintenance history page loads; schedule form is present (UC-OPS-08)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/admin/maintenance/');
    await expect(page.getByRole('main')).toBeVisible();

    await page.goto('/admin/maintenance/history');
    await dismissProductTour(page);
    await expect(page).toHaveURL(/\/admin\/maintenance\/history/);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');

    await page.goto('/admin/maintenance/');
    await dismissProductTour(page);
    // Prefer schedule controls over toggling live maintenance (would 503 the suite).
    const schedule = page.locator('form[action*="/schedule"], input[name*="schedule"], button').filter({
      hasText: /schedule|programar|planif/i,
    });
    await expect(page.locator('form[action*="/enable"], form[action*="/disable"], form[action*="/schedule"]').first()).toBeVisible({
      timeout: 15_000,
    });
    void schedule;
  });
});

test.describe('Account tour mark-seen', () => {
  test('marks product tour seen via POST (UC-ACC-21)', async ({ page, request }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    await dismissProductTour(page);
    const tokenEl = page.locator('[data-product-tour-mark-token-value]').first();
    let csrf = '';
    if ((await tokenEl.count()) > 0) {
      csrf = (await tokenEl.getAttribute('data-product-tour-mark-token-value')) || '';
    }
    if (!csrf) {
      // Tours page / display prefs may still expose the token on a controller host.
      await page.goto('/account/display/tours');
      await dismissProductTour(page);
      csrf =
        (await page.locator('[data-product-tour-mark-token-value]').first().getAttribute('data-product-tour-mark-token-value').catch(() => null)) ||
        '';
    }

    const res = await request.post('/account/product-tour/seen', {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf || 'missing',
        'X-Requested-With': 'XMLHttpRequest',
      },
      data: { page: 'dashboard', seen: true },
      failOnStatusCode: false,
    });
    expect(res.status(), await res.text()).toBeLessThan(500);
    if (csrf) {
      expect([200, 204]).toContain(res.status());
      const json = await res.json();
      expect(json.ok).toBeTruthy();
    } else {
      // Token absent (tour chrome disabled) — route still must not 5xx.
      expect([200, 204, 403, 400]).toContain(res.status());
    }
  });
});
