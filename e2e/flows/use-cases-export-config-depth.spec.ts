import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
  dismissProductTour,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

async function gotoDownload(page: import('@playwright/test').Page, url: string) {
  const [download] = await Promise.all([
    page.waitForEvent('download', { timeout: 20_000 }).catch(() => null),
    page.goto(url, { waitUntil: 'commit' }).catch((err: Error) => {
      if (!/Download is starting/i.test(err.message)) {
        throw err;
      }
      return null;
    }),
  ]);
  return download;
}

function parseCsvLevels(csv: string): string[] {
  const lines = csv.trim().split(/\r?\n/).filter(Boolean);
  if (lines.length < 2) {
    return [];
  }
  const parseRow = (line: string): string[] => {
    const cols: string[] = [];
    let cur = '';
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
      const ch = line[i];
      if (ch === '"') {
        if (inQuotes && line[i + 1] === '"') {
          cur += '"';
          i++;
        } else {
          inQuotes = !inQuotes;
        }
      } else if (ch === ',' && !inQuotes) {
        cols.push(cur);
        cur = '';
      } else {
        cur += ch;
      }
    }
    cols.push(cur);
    return cols.map((c) => c.trim());
  };
  const header = parseRow(lines[0]).map((h) => h.toLowerCase());
  const levelIdx = header.findIndex((h) => h === 'level' || h === 'severity');
  if (levelIdx < 0) {
    return [];
  }
  const levels: string[] = [];
  for (const line of lines.slice(1)) {
    const cols = parseRow(line);
    const raw = (cols[levelIdx] ?? '').toLowerCase();
    if (raw) {
      levels.push(raw);
    }
  }
  return levels;
}

test.describe('Export & config depth', () => {
  test('issues CSV export with level=error only contains error rows (UC-ISS-10-D1)', async ({ page }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    await page.goto(`/projects/${uuid}/issues?level=error&status=unresolved`);
    await dismissProductTour(page);
    const rows = page.locator('table.issue-table tbody tr');
    requireSampleOrSkip((await rows.count()) > 0, 'No unresolved error issues — run make seed-sample');

    const download = await gotoDownload(page, `/projects/${uuid}/export/issues.csv?level=error&status=unresolved`);
    expect(download, 'Expected issues CSV download').not.toBeNull();
    const tmp = path.join(os.tmpdir(), download!.suggestedFilename() || `issues-${Date.now()}.csv`);
    await download!.saveAs(tmp);
    const csv = fs.readFileSync(tmp, 'utf8');
    expect(csv.length).toBeGreaterThan(10);
    const levels = parseCsvLevels(csv);
    expect(levels.length, 'CSV should include at least one data row').toBeGreaterThan(0);
    for (const level of levels) {
      expect(level).toBe('error');
    }
  });

  test('project config import mutates retention_days then restores (UC-PROJ-16-D1)', async ({ page }) => {
    test.setTimeout(120_000);
    const uuid = await resolveDemoProjectUuid(page);

    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    const retention = page.locator('#project_governance_retention_days');
    await expect(retention).toBeVisible({ timeout: 15_000 });
    const original = await retention.inputValue();

    const download = await gotoDownload(page, `/projects/${uuid}/config/export`);
    expect(download, 'Expected project config JSON download').not.toBeNull();
    const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'beacon-e2e-ret-'));
    const jsonPath = path.join(tmpDir, download!.suggestedFilename() || 'beacon-project.json');
    await download!.saveAs(jsonPath);

    const payload = JSON.parse(fs.readFileSync(jsonPath, 'utf8')) as {
      projects?: Array<{ retention_days?: number; [k: string]: unknown }>;
    };
    expect(Array.isArray(payload.projects) && payload.projects.length > 0).toBeTruthy();
    const target = Number(original) === 41 ? 42 : 41;
    payload.projects![0].retention_days = target;
    const mutatedPath = path.join(tmpDir, 'mutated.json');
    fs.writeFileSync(mutatedPath, JSON.stringify(payload, null, 2));

    await page.goto(`/projects/${uuid}/settings/data`);
    await dismissProductTour(page);
    const panel = page.locator('[data-testid="project-config-portability"]');
    await panel.locator('#tab-project-config-import').click();
    await panel.locator('[data-testid="project-config-file"]').setInputFiles(mutatedPath);
    await panel.locator('[data-testid="project-config-import-submit"]').click();
    await waitForPageLoader(page);

    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    await expect(page.locator('#project_governance_retention_days')).toHaveValue(String(target), { timeout: 15_000 });

    // Restore original export so shared demo stays stable.
    await page.goto(`/projects/${uuid}/settings/data`);
    await dismissProductTour(page);
    await panel.locator('#tab-project-config-import').click();
    await panel.locator('[data-testid="project-config-file"]').setInputFiles(jsonPath);
    await panel.locator('[data-testid="project-config-import-submit"]').click();
    await waitForPageLoader(page);

    await page.goto(`/projects/${uuid}/settings/general`);
    await dismissProductTour(page);
    await expect(page.locator('#project_governance_retention_days')).toHaveValue(original, { timeout: 15_000 });
  });
});
