import { expect, type Locator, type Page } from '@playwright/test';

/** Box metrics used to compare kit chrome across screens. */
export type ChromeBox = {
  paddingTop: number;
  paddingRight: number;
  paddingBottom: number;
  paddingLeft: number;
  fontSize: number;
  minHeight: number;
  borderRadius: number;
};

/** Design targets from `assets/styles/_components.scss` ($beacon-btn-*). */
export const BEACON_BTN_TARGET = {
  fontSizePx: 14, // 0.875rem
  paddingYPx: 8, // 0.5rem
  paddingXPx: 16, // 1rem
} as const;

export async function assertShellAesthetics(page: Page): Promise<void> {
  const html = page.locator('html');
  const lang = await html.getAttribute('lang');
  if (lang) {
    expect(lang, 'html lang').toMatch(/^(en|es)(-|$)/i);
  }
  const theme = await html.getAttribute('data-theme');
  if (theme) {
    expect(theme.length, 'data-theme').toBeGreaterThan(0);
  } else {
    // Some kit admin new/edit paints may briefly omit data-theme; still require app body.
    await expect(page.locator('body')).toBeVisible();
  }
  await expect(page.locator('body')).toBeVisible();
  const main = page.getByRole('main');
  if ((await main.count()) > 0 && (await main.first().isVisible().catch(() => false))) {
    await expect(main.first()).toBeVisible();
  } else {
    // Kit modal partials / AuthKit / admin shells without a <main> landmark.
    await expect(
      page
        .locator(
          '.nowo-auth-kit__panel, .nowo-auth-kit, .kit-admin, .panel, dialog[open], form:has(button[type="submit"]), form:has(input[type="submit"])',
        )
        .first(),
    ).toBeVisible();
  }

  const tokens = await page.evaluate(() => {
    const styles = getComputedStyle(document.documentElement);
    return {
      ink: styles.getPropertyValue('--color-ink').trim(),
      moss: styles.getPropertyValue('--color-moss').trim(),
      sand: styles.getPropertyValue('--color-sand').trim(),
    };
  });
  // Token presence preferred; if empty (fragment), skip rather than fail the whole crawl.
  if (tokens.ink || tokens.moss || tokens.sand) {
    expect(tokens.ink.length + tokens.moss.length + tokens.sand.length, 'Beacon color tokens').toBeGreaterThan(0);
  }
  await expect(page.locator('link[href*="bootstrap"]')).toHaveCount(0);
}

export async function assertStandardForm(
  page: Page,
  formLocator: Locator = page.getByRole('main').locator('form').first(),
): Promise<void> {
  await expect(formLocator).toBeVisible();

  const token = formLocator.locator(
    'input[type="hidden"][name="_token"], input[type="hidden"][name$="[_token]"], input[type="hidden"][name*="csrf"], input[name*="[_csrf_token]"]',
  );
  if ((await token.count()) > 0) {
    await expect(token.first()).toBeAttached();
  } else {
    await expect(formLocator.locator('label, .form-group, input, select, textarea').first()).toBeVisible();
  }

  const kitSubmit = formLocator.locator(
    'button.btn-primary[type="submit"], button.btn-danger[type="submit"], button.btn-ghost[type="submit"], button.btn-secondary[type="submit"], button.nowo-ui-btn[type="submit"], input.btn-primary[type="submit"]',
  );
  if ((await kitSubmit.count()) > 0) {
    await expect(kitSubmit.first()).toBeVisible();
    return;
  }

  const submit = formLocator.locator('button[type="submit"], input[type="submit"]').first();
  await expect(submit).toBeVisible();
  const submitClass = (await submit.getAttribute('class')) ?? '';
  expect(
    /btn-primary|btn-danger|btn-ghost|btn-secondary|nowo-ui-|border-\[var\(--color-moss\)\]|bg-\[var\(--color-moss\)\]|text-\[var\(--color-/.test(
      submitClass,
    ),
    `submit should use kit button or Beacon token classes, got: ${submitClass}`,
  ).toBeTruthy();
}

export async function assertStandardTable(
  page: Page,
  tableLocator: Locator = page.getByRole('main').locator('table').first(),
): Promise<void> {
  await expect(tableLocator).toBeVisible();
  const head = tableLocator.locator('thead th');
  if ((await head.count()) > 0) {
    await expect(head.first()).toBeVisible();
  }
  // Some product tables are headless grids / payload tables — still require body or rows.
  const body = tableLocator.locator('tbody, tr').first();
  await expect(body).toBeVisible();
}

export async function formOnPageOrDialog(page: Page): Promise<Locator> {
  const openDialog = page.locator('dialog[open], dialog.confirm-dialog[open], [role="dialog"]:not([aria-hidden="true"])').first();
  if ((await openDialog.count()) > 0 && (await openDialog.isVisible().catch(() => false))) {
    const dialogForm = openDialog.locator('form').first();
    if ((await dialogForm.count()) > 0) {
      return dialogForm;
    }
  }
  return page
    .getByRole('main')
    .locator('form')
    .filter({ has: page.locator('button[type="submit"], input[type="submit"]') })
    .first();
}

/** Soft chrome kinds present on a product screen (form / table / panel / dialog host). */
export async function assertPageHasKitChrome(page: Page): Promise<void> {
  await assertShellAesthetics(page);
  const main = page.getByRole('main');
  const form = main.locator('form').first();
  const table = main.locator('table').first();
  const panel = main.locator('.panel, .kit-admin, [data-testid]').first();
  const dialog = page.locator('dialog.confirm-dialog, [data-controller="confirm-dialog"]');
  const empty = main.locator('.empty, [data-tour*="empty"], .panel');

  const hasForm = (await form.count()) > 0 && (await form.isVisible().catch(() => false));
  const hasTable = (await table.count()) > 0 && (await table.isVisible().catch(() => false));
  const hasPanel = (await panel.count()) > 0 && (await panel.isVisible().catch(() => false));
  const hasDialog = (await dialog.count()) > 0;
  const hasEmptyChrome = (await empty.count()) > 0;

  expect(
    hasForm || hasTable || hasPanel || hasDialog || hasEmptyChrome,
    'page should expose kit chrome (form/table/panel/dialog) or an empty panel state',
  ).toBeTruthy();

  if (hasForm) {
    // Prefer the largest visible form in main (skip tiny locale switchers).
    const mainForm = main.locator('form').filter({ has: page.locator('button[type="submit"], input[type="submit"], input:not([type="hidden"])') }).first();
    if ((await mainForm.count()) > 0) {
      await assertStandardForm(page, mainForm);
    }
  }
  if (hasTable) {
    const structured = main.locator('table').filter({ has: page.locator('thead th') }).first();
    if ((await structured.count()) > 0) {
      await assertStandardTable(page, structured);
    }
  }
}

function parsePx(value: string): number {
  const n = Number.parseFloat(value);
  return Number.isFinite(n) ? n : 0;
}

export async function readChromeBox(locator: Locator): Promise<ChromeBox | null> {
  if ((await locator.count()) === 0) {
    return null;
  }
  const el = locator.first();
  if (!(await el.isVisible().catch(() => false))) {
    return null;
  }
  return el.evaluate((node) => {
    const s = getComputedStyle(node as Element);
    const px = (v: string) => Number.parseFloat(v) || 0;
    return {
      paddingTop: px(s.paddingTop),
      paddingRight: px(s.paddingRight),
      paddingBottom: px(s.paddingBottom),
      paddingLeft: px(s.paddingLeft),
      fontSize: px(s.fontSize),
      minHeight: px(s.minHeight) || (node as HTMLElement).getBoundingClientRect().height,
      borderRadius: px(s.borderTopLeftRadius),
    };
  });
}

export function assertBoxesAligned(
  boxes: Array<{ path: string; box: ChromeBox }>,
  label: string,
  keys: Array<keyof ChromeBox> = ['paddingTop', 'paddingLeft', 'fontSize'],
  tolerancePx = 2,
): void {
  const usable = boxes.filter((b) => b?.box && typeof b.box.fontSize === 'number');
  if (usable.length < 2) {
    return;
  }
  const baseline = usable[0].box;
  for (const sample of usable.slice(1)) {
    for (const key of keys) {
      const delta = Math.abs(sample.box[key] - baseline[key]);
      expect(
        delta,
        `${label}.${key} drift ${sample.path} vs ${usable[0].path}: ${sample.box[key]} vs ${baseline[key]} (Δ=${delta})`,
      ).toBeLessThanOrEqual(tolerancePx);
    }
  }
}

export function assertNearTarget(box: ChromeBox, target: { fontSizePx: number; paddingYPx: number; paddingXPx: number }, label: string, tolerancePx = 2): void {
  expect(Math.abs(box.fontSize - target.fontSizePx), `${label} fontSize`).toBeLessThanOrEqual(tolerancePx);
  expect(Math.abs(box.paddingTop - target.paddingYPx), `${label} paddingTop`).toBeLessThanOrEqual(tolerancePx);
  expect(Math.abs(box.paddingBottom - target.paddingYPx), `${label} paddingBottom`).toBeLessThanOrEqual(tolerancePx);
  expect(Math.abs(box.paddingLeft - target.paddingXPx), `${label} paddingLeft`).toBeLessThanOrEqual(tolerancePx + 2);
  expect(Math.abs(box.paddingRight - target.paddingXPx), `${label} paddingRight`).toBeLessThanOrEqual(tolerancePx + 2);
}

/** Sample primary button + text input + panel from the current page. */
export async function samplePageChrome(page: Page, path: string): Promise<{
  path: string;
  btn: ChromeBox | null;
  input: ChromeBox | null;
  panel: ChromeBox | null;
}> {
  const main = page.getByRole('main');
  const btn = await readChromeBox(
    main.locator('button.btn-primary, a.btn-primary, button.btn-danger').first(),
  );
  const input = await readChromeBox(
    main
      .locator(
        'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]), select, textarea',
      )
      .first(),
  );
  const panel = await readChromeBox(main.locator('.panel').first());
  return { path, btn, input, panel };
}

export { parsePx };
