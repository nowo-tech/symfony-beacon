import { expect, type Locator, type Page } from '@playwright/test';

/**
 * Pixel-lock for Beacon section tabs (UC-UI-14).
 *
 * Manual screenshots showed the first tab jumping 1–2px depending on which
 * sibling was active: bold glyphs + `align-items: center` change the box.
 * Same tags/classes must share the same row `y` and `height`.
 */

export type TabGeom = {
  tag: string;
  y: number;
  height: number;
  fontWeight: string;
  className: string;
};

const TAB_ITEM_SELECTOR = 'nav a, a.nowo-ui-tabs__item, [role="tab"]';

export async function readTabGeoms(root: Locator): Promise<TabGeom[]> {
  const items = root.locator(TAB_ITEM_SELECTOR);
  const count = await items.count();
  const out: TabGeom[] = [];
  for (let i = 0; i < count; i++) {
    const item = items.nth(i);
    if (!(await item.isVisible().catch(() => false))) {
      continue;
    }
    out.push(
      await item.evaluate((node) => {
        const el = node as HTMLElement;
        const r = el.getBoundingClientRect();
        return {
          tag: el.tagName,
          y: Math.round(r.top),
          height: Math.round(r.height),
          fontWeight: getComputedStyle(el).fontWeight,
          className: el.className.toString(),
        };
      }),
    );
  }
  return out;
}

/** Cluster items that sit on the same visual row (wrapped flex). */
export function groupTabRows(items: TabGeom[], rowTolerancePx = 2): TabGeom[][] {
  const sorted = [...items].sort((a, b) => a.y - b.y);
  const rows: TabGeom[][] = [];
  for (const item of sorted) {
    const row = rows.find((candidate) => Math.abs(candidate[0].y - item.y) <= rowTolerancePx);
    if (row) {
      row.push(item);
    } else {
      rows.push([item]);
    }
  }
  return rows;
}

export async function assertTablistGeometry(
  root: Locator,
  label: string,
  tolerancePx = 1,
): Promise<void> {
  const items = await readTabGeoms(root);
  if (items.length < 2) {
    return;
  }

  const tags = new Set(items.map((i) => i.tag));
  expect(tags.size, `${label}: mixed HTML tags ${[...tags].join(',')}`).toBe(1);

  const weights = new Set(items.map((i) => i.fontWeight));
  expect(
    weights.size,
    `${label}: font-weight must not change between active/inactive (got ${[...weights].join(',')})`,
  ).toBe(1);

  const heights = items.map((i) => i.height);
  const heightMin = Math.min(...heights);
  const heightMax = Math.max(...heights);
  expect(
    heightMax - heightMin,
    `${label}: tab height drift ${heightMin}–${heightMax}px`,
  ).toBeLessThanOrEqual(tolerancePx);

  for (const row of groupTabRows(items)) {
    if (row.length < 2) {
      continue;
    }
    const ys = row.map((i) => i.y);
    expect(
      Math.max(...ys) - Math.min(...ys),
      `${label}: first-tab y jump on a row (${ys.join(',')})`,
    ).toBeLessThanOrEqual(tolerancePx);
  }
}

/**
 * Lock every Beacon / UiKit tab strip on the page.
 * Skips empty wrappers (panel chrome without items).
 */
export async function assertPageTabGeometry(page: Page, tolerancePx = 1): Promise<void> {
  const lists = page.locator('.beacon-tabs, [role="tablist"]');
  const n = await lists.count();
  let asserted = 0;
  for (let i = 0; i < n; i++) {
    const list = lists.nth(i);
    if (!(await list.isVisible().catch(() => false))) {
      continue;
    }
    const geoms = await readTabGeoms(list);
    if (geoms.length < 2) {
      continue;
    }
    await assertTablistGeometry(list, `${page.url()} tablist[${i}]`, tolerancePx);
    asserted += 1;
  }
  expect(asserted, `${page.url()} expected at least one tab strip`).toBeGreaterThan(0);
}
