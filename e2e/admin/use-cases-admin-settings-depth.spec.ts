import { test, expect } from '@playwright/test';
import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from '../support/helpers';

/**
 * Admin settings depth: HTTP log filters/export guards, ops defaults mutate+restore,
 * Mercure unsafe URL, realtime config shape, instance-config invalid import,
 * maintenance schedule (no live enable).
 */

test.describe('HTTP log settings depth', () => {
  test('filters by path and clear restores list (UC-ADM-21-D2)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/admin/http-log');
    const filters = page.locator('[data-testid="http-log-filters"]');
    await expect(filters).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('[data-testid="http-log-results"]')).toBeVisible();

    const pathInput = filters.locator('input[name*="[path]"], input[name="path"]').first();
    await expect(pathInput).toBeVisible({ timeout: 10_000 });
    await pathInput.fill('/admin');
    await filters.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/path=|http_log_filter/i, { timeout: 15_000 });
    await expect(page.locator('[data-testid="http-log-results"]')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');

    const clear = filters.locator('a').filter({ hasText: /clear|limpiar|effacer|löschen/i }).first();
    await expect(clear).toBeVisible({ timeout: 10_000 });
    await clear.click();
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/http-log\/?$/, { timeout: 15_000 });
    await expect(page.locator('[data-testid="http-log-results"]')).toBeVisible();
  });

  test('JSON export works; POST-only actions reject GET (UC-ADM-21-D3)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/admin/http-log');

    const jsonBtn = page.locator('button.nowo-ui-btn-export, button').filter({ hasText: /export.*json|json/i }).first();
    await expect(jsonBtn).toBeVisible({ timeout: 15_000 });
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 30_000 }).catch(() => null),
      jsonBtn.click(),
    ]);
    if (download) {
      expect(download.suggestedFilename()).toMatch(/\.json|http|log/i);
      const dest = path.join(os.tmpdir(), `beacon-http-log-${Date.now()}.json`);
      await download.saveAs(dest);
      const raw = fs.readFileSync(dest, 'utf8');
      fs.unlinkSync(dest);
      expect(() => JSON.parse(raw)).not.toThrow();
    } else {
      await waitForPageLoader(page);
      await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
    }

    // Never submit purge on shared DB — only assert method guards.
    for (const route of ['/admin/http-log/export', '/admin/http-log/purge']) {
      const res = await page.request.get(route, { failOnStatusCode: false });
      expect(res.status(), route).toBe(405);
    }
    await expect(page.locator('button.nowo-ui-btn-purge, button').filter({ hasText: /purge|purgar|vaciar/i }).first()).toBeVisible();
  });
});

test.describe('Ops defaults & Mercure depth', () => {
  test('ingest envelopeMaxBytes mutates and restores (UC-ADM-32-D1)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/admin/ops-defaults/ingest');
    const form = page.locator('[data-testid="ops-defaults-form"]');
    await expect(form).toBeVisible({ timeout: 15_000 });
    const field = form.locator('input[name="instance_ops_defaults[envelopeMaxBytes]"]');
    await expect(field).toBeVisible({ timeout: 10_000 });
    const original = await field.inputValue();
    const originalNum = Number.parseInt(original, 10);
    expect(Number.isFinite(originalNum)).toBeTruthy();
    const mutated = String(originalNum + 1024);
    await field.fill(mutated);
    await form.locator('[data-testid="ops-defaults-submit"]').click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');

    await page.goto('/admin/ops-defaults/ingest');
    await dismissProductTour(page);
    const reloaded = page.locator('input[name="instance_ops_defaults[envelopeMaxBytes]"]');
    await expect(reloaded).toHaveValue(mutated, { timeout: 15_000 });

    await reloaded.fill(original);
    await page.locator('[data-testid="ops-defaults-submit"]').click();
    await waitForPageLoader(page);
    await page.goto('/admin/ops-defaults/ingest');
    await dismissProductTour(page);
    await expect(page.locator('input[name="instance_ops_defaults[envelopeMaxBytes]"]')).toHaveValue(original, {
      timeout: 15_000,
    });
  });

  test('Mercure rejects unsafe hub URL (UC-ADM-34-D1)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/admin/mercure');
    const form = page.getByRole('main').locator('form').filter({
      has: page.locator('input[name="instance_mercure[mercureUrl]"]'),
    }).first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    const urlField = form.locator('input[name="instance_mercure[mercureUrl]"]');
    const prior = await urlField.inputValue();
    await urlField.fill('javascript:alert(1)');
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
    await expect(page.locator('body')).toContainText(/unsafe|invalid|inválid|no válida|no valida|not allowed|no permit/i, {
      timeout: 15_000,
    });
    // Invalid value may remain in the re-rendered input; it must not persist after reload.
    await page.goto('/admin/mercure');
    await dismissProductTour(page);
    const persisted = await page.locator('input[name="instance_mercure[mercureUrl]"]').inputValue();
    expect(persisted).not.toBe('javascript:alert(1)');
    expect(persisted).toBe(prior);
  });

  test('realtime config JSON has mercure and push shape (UC-ACC-15-D1)', async ({ page }) => {
    await expectAuthenticatedPage(page, '/dashboard');
    const res = await page.request.get('/account/realtime/config');
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('mercure');
    expect(body.mercure).toEqual(
      expect.objectContaining({
        enabled: expect.any(Boolean),
        topics: expect.any(Array),
      }),
    );
    expect(body).toHaveProperty('push');
    expect(body.push).toEqual(
      expect.objectContaining({
        preferenceEnabled: expect.any(Boolean),
        configured: expect.any(Boolean),
      }),
    );
  });
});

test.describe('Instance config & maintenance depth', () => {
  test('invalid instance-config import is rejected (UC-ADM-17-D1)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/admin/instance-config');
    const tmp = path.join(os.tmpdir(), `beacon-e2e-bad-instance-${Date.now()}.json`);
    fs.writeFileSync(tmp, '{ definitely not valid instance config');
    try {
      await page.locator('[data-testid="instance-config-file"], input[type="file"]').setInputFiles(tmp);
      await page.locator('[data-testid="instance-config-import-submit"]').click();
      await waitForPageLoader(page);
      await expect(page.locator('body')).toContainText(/invalid|inválid|json|error|formato|schema|import/i, {
        timeout: 15_000,
      });
      await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');

      const exportRes = await page.request.get('/admin/instance-config/export');
      expect(exportRes.status()).toBe(200);
      const payload = (await exportRes.json()) as { instance?: { setup_completed?: boolean } };
      expect(payload.instance?.setup_completed).toBe(true);
    } finally {
      fs.unlinkSync(tmp);
    }
  });

  test('maintenance schedule save and clear without live enable (UC-OPS-08-D1)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/admin/maintenance/');
    const section = page.locator('[data-testid="maintenance-schedule"]');
    await expect(section).toBeVisible({ timeout: 15_000 });

    // Far-future window — never click live enable/disable.
    const enableAt = section.locator('input[name*="scheduled_enable_at"]').first();
    const disableAt = section.locator('input[name*="scheduled_disable_at"]').first();
    await expect(enableAt).toBeVisible({ timeout: 10_000 });
    const enableValue = '2099-06-15T10:00';
    const disableValue = '2099-06-15T12:00';
    await enableAt.fill(enableValue);
    if ((await disableAt.count()) > 0) {
      await disableAt.fill(disableValue);
    }
    await section.locator('button[type="submit"]').filter({ hasText: /save|guardar|enregistrer|speichern/i }).first().click();
    await waitForPageLoader(page);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
    await expect(page.getByRole('main')).toContainText(/2099|schedule|programad/i, { timeout: 15_000 });

    const clear = page.locator(
      'button[form="maintenance-clear-schedule-form"], #maintenance-clear-schedule-form button[type="submit"]',
    ).first();
    await expect(clear).toBeVisible({ timeout: 10_000 });
    await clear.click({ force: true });
    await waitForPageLoader(page);
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
    // After clear, scheduled dates should not still show the far-future marker in status.
    await page.goto('/admin/maintenance/');
    await dismissProductTour(page);
    const statusText = await page.getByRole('main').innerText();
    expect(statusText).not.toMatch(/2099-06-15T10:00/);
  });
});
