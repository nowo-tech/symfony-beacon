/**
 * Setup wizard screenshots for docs/manual/ (cold disposable stack only).
 * Day theme + English only (theme/locale demos live in prefs-* product shots).
 *
 * Run: make docs-manual-screenshots-setup
 * Prereq: make wipe-e2e-cold && make up-e2e-cold
 */
import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  captureManualScreenshot,
  dismissCookieConsent,
  dismissProductTour,
  gotoStable,
  MANUAL_VIEWPORT,
} from '../support/helpers';
import {
  COLD_ADMIN_EMAIL,
  COLD_ADMIN_PASSWORD,
  COLD_SETUP_TOKEN,
  getSetupProgress,
  postSetupAdvance,
  runFreshInstallCircuit,
} from '../support/setup-cold';

const rootDir = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const outDir = path.join(rootDir, 'docs', 'manual', 'images');

test.describe.configure({ mode: 'serial' });

test.describe('Setup wizard manual screenshots', () => {
  test.use({
    storageState: { cookies: [], origins: [] },
    viewport: { ...MANUAL_VIEWPORT },
  });

  test('capture setup gate and wizard steps', async ({ page, request }) => {
    await page.setViewportSize(MANUAL_VIEWPORT);

    await gotoStable(page, '/login');
    await dismissCookieConsent(page);
    await expect(page).toHaveURL(/\/setup(\?|$|\/)/);
    await captureManualScreenshot(page, outDir, 'setup-gate', { theme: 'light', maxParts: 1 });

    await gotoStable(page, `/setup?token=${encodeURIComponent(COLD_SETUP_TOKEN)}`);
    await dismissCookieConsent(page);
    await dismissProductTour(page);
    await captureManualScreenshot(page, outDir, 'setup-wizard', { theme: 'light', maxParts: 2 });

    let progress = await postSetupAdvance(request, {
      profile: 'fresh_install',
      bootstrap_mode: 'guided',
    });
    for (let i = 0; i < 25 && progress.phase !== 'failed' && progress.phase !== 'completed'; i++) {
      await gotoStable(page, `/setup?token=${encodeURIComponent(COLD_SETUP_TOKEN)}`);
      await dismissCookieConsent(page);
      const step = (progress.current_step_id ?? progress.phase ?? 'step').replace(/[^a-z0-9_-]+/gi, '-').toLowerCase();

      if (/admin/i.test(progress.current_step_id ?? '') || /admin/i.test(progress.message ?? '')) {
        await captureManualScreenshot(page, outDir, 'setup-admin', { theme: 'light', maxParts: 2 });
        progress = await postSetupAdvance(request, {
          email: COLD_ADMIN_EMAIL,
          password: COLD_ADMIN_PASSWORD,
        });
        break;
      }
      if (progress.phase === 'waiting_input' && /sample/i.test(progress.current_step_id ?? '')) {
        await captureManualScreenshot(page, outDir, 'setup-sample', { theme: 'light', maxParts: 1 });
        progress = await postSetupAdvance(request, { action: 'skip' });
        continue;
      }
      if (progress.phase === 'waiting_input' && /database_url/i.test(progress.current_step_id ?? '')) {
        progress = await postSetupAdvance(request, { action: 'skip' });
        continue;
      }
      if (progress.phase === 'waiting_input' && /bootstrap/i.test(progress.current_step_id ?? '')) {
        progress = await postSetupAdvance(request, { bootstrap_mode: 'guided' });
        continue;
      }
      if (i === 2) {
        await captureManualScreenshot(page, outDir, `setup-progress-${step}`.slice(0, 48), {
          theme: 'light',
          maxParts: 1,
        });
      }
      progress = await postSetupAdvance(request, {});
    }

    const before = await getSetupProgress(request);
    if (!('redirectedHome' in before) && before.phase !== 'completed') {
      await runFreshInstallCircuit(request);
    }

    await gotoStable(page, '/setup/done');
    await dismissCookieConsent(page);
    if (page.url().includes('/setup')) {
      await captureManualScreenshot(page, outDir, 'setup-done', { theme: 'light', maxParts: 1 });
    }
  });
});
