import { expect, type APIRequestContext } from '@playwright/test';

/** Local / E2E default — see `.env.e2e.dist` and `beacon.local_setup_token`. */
export const COLD_SETUP_TOKEN = process.env.PLAYWRIGHT_SETUP_TOKEN ?? 'beacon-local-setup';

export const COLD_ADMIN_EMAIL = process.env.PLAYWRIGHT_COLD_ADMIN_EMAIL ?? 'cold-admin@symfony-beacon.local';
export const COLD_ADMIN_PASSWORD = process.env.PLAYWRIGHT_COLD_ADMIN_PASSWORD ?? 'ColdAdmin1!x';

export type SetupProgress = {
  phase?: string;
  profile?: string;
  current_step_id?: string | null;
  percent?: number;
  message?: string | null;
  error?: string | null;
  completed_step_ids?: string[];
};

function setupHeaders(): Record<string, string> {
  return {
    'X-Setup-Token': COLD_SETUP_TOKEN,
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
}

export async function getSetupProgress(request: APIRequestContext): Promise<SetupProgress | { redirectedHome: true }> {
  const res = await request.get('/setup/api/progress', { failOnStatusCode: false, maxRedirects: 0 });
  // After durable setup.done, SiteBackup may 302 wizard + API to home.
  if ([301, 302, 303, 307, 308].includes(res.status())) {
    expect(res.headers()['location'] ?? '').toMatch(/\/($|\?)/);
    return { redirectedHome: true };
  }
  expect(res.status(), await res.text()).toBe(200);
  return (await res.json()) as SetupProgress;
}

export async function postSetupAdvance(
  request: APIRequestContext,
  data: Record<string, unknown> = {},
): Promise<SetupProgress> {
  const res = await request.post('/setup/api/advance', {
    headers: setupHeaders(),
    data,
    failOnStatusCode: false,
    timeout: 600_000,
  });
  const text = await res.text();
  expect(res.status(), text).toBe(200);
  return JSON.parse(text) as SetupProgress;
}

function isAdminStep(progress: SetupProgress): boolean {
  const id = progress.current_step_id ?? '';
  return progress.phase === 'waiting_input' && /admin/i.test(id);
}

function isSampleStep(progress: SetupProgress): boolean {
  const id = progress.current_step_id ?? '';
  return progress.phase === 'waiting_input' && /sample/i.test(id);
}

/**
 * Drive SiteBackup `fresh_install` guided profile via API (manual advance_mode).
 * Skips optional database_url + sample; provisions admin; waits for phase=completed.
 */
export async function runFreshInstallCircuit(request: APIRequestContext): Promise<SetupProgress> {
  let progress = await postSetupAdvance(request, {
    profile: 'fresh_install',
    bootstrap_mode: 'guided',
  });

  const maxSteps = 40;
  for (let i = 0; i < maxSteps; i++) {
    if (progress.phase === 'failed') {
      throw new Error(`Setup failed: ${progress.error ?? progress.message ?? JSON.stringify(progress)}`);
    }
    if (progress.phase === 'completed') {
      return progress;
    }

    if (isAdminStep(progress)) {
      progress = await postSetupAdvance(request, {
        email: COLD_ADMIN_EMAIL,
        password: COLD_ADMIN_PASSWORD,
      });
      continue;
    }

    if (isSampleStep(progress)) {
      progress = await postSetupAdvance(request, { action: 'skip' });
      continue;
    }

    // Optional database_url waiting_input — empty/skip uses Compose DATABASE_URL.
    if (progress.phase === 'waiting_input' && /database_url/i.test(progress.current_step_id ?? '')) {
      progress = await postSetupAdvance(request, { action: 'skip' });
      continue;
    }

    if (progress.phase === 'waiting_input' && /bootstrap/i.test(progress.current_step_id ?? '')) {
      progress = await postSetupAdvance(request, { bootstrap_mode: 'guided' });
      continue;
    }

    // Auto / confirm steps: empty POST advances one (manual mode).
    progress = await postSetupAdvance(request, {});
  }

  throw new Error(`Setup did not complete within ${maxSteps} advances: ${JSON.stringify(progress)}`);
}
