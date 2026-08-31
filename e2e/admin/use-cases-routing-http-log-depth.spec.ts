import { test, expect } from '@playwright/test';
import { dismissProductTour, expectAuthenticatedPage, waitForPageLoader } from '../support/helpers';

/**
 * RoutingKit import/export round-trip + HTTP log show/delete (no mass purge).
 */

test.describe('RoutingKit import/export depth', () => {
  test('export then import restores ephemeral path (UC-ADM-24-D2)', async ({ page }) => {
    test.setTimeout(120_000);
    const suffix = Date.now().toString(36);
    const pathValue = `/e2e-rk-ie-${suffix}`;

    await expectAuthenticatedPage(page, '/admin/_routing/new');
    const form = page.locator('[data-testid="routing-kit-definition-form"] form, [data-testid="routing-kit-definition-form"]').first();
    await expect(form).toBeVisible({ timeout: 15_000 });
    const routeSelect = form.locator('select[name="route_name"]');
    const optionCount = await routeSelect.locator('option').count();
    if (optionCount === 0) {
      test.info().annotations.push({ type: 'note', description: 'no Routable candidates — skip' });
      return;
    }
    await routeSelect.selectOption({ index: 0 });
    await form.locator('input[name="path"]').fill(pathValue);
    await form.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);

    await page.goto('/admin/_routing/');
    await dismissProductTour(page);
    await expect(page.locator('tr').filter({ hasText: pathValue })).toBeVisible({ timeout: 15_000 });

    const exportForm = page.locator('form[action$="/export"], form[action*="/export"]').first();
    await expect(exportForm).toBeVisible({ timeout: 10_000 });
    const csrf = await exportForm.locator('input[name="_csrf_token"]').inputValue();
    const exportRes = await page.request.post('/admin/_routing/export', {
      form: { confirmed: '1', _csrf_token: csrf },
      failOnStatusCode: false,
    });
    expect(exportRes.status(), await exportRes.text()).toBe(200);
    const envelope = await exportRes.json();
    expect(envelope).toHaveProperty('signature');
    expect(JSON.stringify(envelope)).toContain(pathValue);

    // Delete ephemeral row before re-import.
    const delRow = page.locator('tr').filter({ hasText: pathValue }).first();
    const deleteBtn = delRow.locator('form[action*="/delete/"] button[type="submit"]').first();
    page.once('dialog', (d) => d.accept().catch(() => undefined));
    await deleteBtn.click();
    await waitForPageLoader(page);
    await expect(page.locator('tr').filter({ hasText: pathValue })).toHaveCount(0, { timeout: 15_000 });

    await page.locator('button').filter({ hasText: /import|importar/i }).first().click();
    const importDialog = page.locator('dialog#modal-routing-import[open], dialog#modal-routing-import').first();
    await expect(importDialog).toBeVisible({ timeout: 15_000 });
    await importDialog.locator('textarea[name="payload_json"]').fill(JSON.stringify(envelope));
    const replaceAll = importDialog.locator('input[name="replace_all"]');
    if ((await replaceAll.count()) > 0 && (await replaceAll.isChecked())) {
      await replaceAll.uncheck();
    }
    await importDialog.locator('button[type="submit"]').first().click({ force: true });
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(pathValue, { timeout: 15_000 });

    // Cleanup.
    await page.goto('/admin/_routing/');
    await dismissProductTour(page);
    const cleanup = page.locator('tr').filter({ hasText: pathValue }).first();
    if ((await cleanup.count()) > 0) {
      page.once('dialog', (d) => d.accept().catch(() => undefined));
      await cleanup.locator('form[action*="/delete/"] button[type="submit"]').first().click();
      await waitForPageLoader(page);
    }
  });

  test('invalid routing import JSON is rejected (UC-ADM-24-D3)', async ({ page }) => {
    test.setTimeout(60_000);
    await expectAuthenticatedPage(page, '/admin/_routing/');
    await page.locator('button').filter({ hasText: /import|importar/i }).first().click();
    const importDialog = page.locator('dialog#modal-routing-import[open], dialog#modal-routing-import').first();
    await expect(importDialog).toBeVisible({ timeout: 15_000 });
    await importDialog.locator('textarea[name="payload_json"]').fill('{ not-valid-json');
    await importDialog.locator('button[type="submit"]').first().click({ force: true });
    await waitForPageLoader(page);
    await expect(page.locator('body')).toContainText(/invalid|inválid|json|error|signature|firma|formato/i, {
      timeout: 15_000,
    });
    await expect(page.locator('body')).not.toContainText('Whoops, looks like something went wrong');
  });
});

test.describe('HTTP log depth', () => {
  test('purge control present without submitting; show and optional delete (UC-ADM-21-D1)', async ({ page }) => {
    test.setTimeout(90_000);
    await expectAuthenticatedPage(page, '/admin/http-log');
    await expect(page.locator('[data-testid="http-log-filters"]')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('[data-testid="http-log-results"]')).toBeVisible();

    // Assert purge affordance only — never submit on shared DB.
    const purge = page.locator('button.nowo-ui-btn-purge, button').filter({ hasText: /purge|purgar|vaciar/i }).first();
    await expect(purge).toBeVisible({ timeout: 15_000 });

    const hrefs = await page.locator('a[href*="/admin/http-log/"]').evaluateAll((anchors) =>
      anchors
        .map((a) => (a as HTMLAnchorElement).getAttribute('href') ?? '')
        .filter((href) => /\/admin\/http-log\/\d+(\/?|$)/.test(href)),
    );
    if (hrefs.length === 0) {
      test.info().annotations.push({ type: 'note', description: 'no http-log rows — show/delete skipped' });
      return;
    }

    await page.goto(hrefs[0]);
    await dismissProductTour(page);
    await expect(page.locator('[data-testid="http-log-show"]')).toBeVisible({ timeout: 15_000 });

    const deleteBtn = page.locator('[data-testid="http-log-show"] form[action*="/delete"] button[type="submit"], [data-testid="http-log-show"] button.btn-danger').first();
    await expect(deleteBtn).toBeVisible({ timeout: 10_000 });

    const showText = await page.locator('[data-testid="http-log-show"]').innerText();
    // Only delete clearly ephemeral e2e traffic; leave production-like rows alone.
    if (!/e2e|playwright|\/e2e-/i.test(showText)) {
      test.info().annotations.push({ type: 'note', description: 'show row not ephemeral — delete not submitted' });
      return;
    }

    const idMatch = page.url().match(/\/admin\/http-log\/(\d+)/);
    expect(idMatch?.[1]).toBeTruthy();
    page.once('dialog', (d) => d.accept().catch(() => undefined));
    await deleteBtn.click({ force: true });
    await waitForPageLoader(page);
    await expect(page).toHaveURL(/\/admin\/http-log\/?$/, { timeout: 15_000 });
  });
});
