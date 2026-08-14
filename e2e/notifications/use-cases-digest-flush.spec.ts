import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * UC-NOTIF-17 — `app:notifications:flush-digests` is console/cron, not a browser surface.
 * `make test-e2e` runs the command and writes `var/e2e/flush-digests.last` before Playwright.
 */
test.describe('Notification digest flush (ops)', () => {
  test('flush-digests artifact shows a successful console run (UC-NOTIF-17)', async () => {
    const artifact = path.join(
      path.dirname(fileURLToPath(import.meta.url)),
      '..',
      '..',
      'var',
      'e2e',
      'flush-digests.last',
    );
    if (!fs.existsSync(artifact)) {
      test.skip(
        true,
        'Missing var/e2e/flush-digests.last — run via `make test-e2e` (writes flush output before Playwright)',
      );
      return;
    }
    const text = fs.readFileSync(artifact, 'utf8');
    expect(text).toMatch(/Flushed \d+ destination/i);
    expect(text).not.toMatch(/\[critical\]|Fatal error|Exception/i);
  });
});
