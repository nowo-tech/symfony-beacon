import { createHmac, randomBytes } from 'node:crypto';
import { test, expect } from '@playwright/test';
import {
  DEMO_EMAIL,
  DEMO_PASSWORD,
  dismissProductTour,
  openFirstIssue,
  requireSampleOrSkip,
  resolveDemoProjectUuid,
  waitForPageLoader,
} from '../support/helpers';

const SLACK_SECRET = 'e2e-slack-neg-secret';
const TEAMS_SECRET = 'e2e-teams-neg-secret';
const INBOUND_SECRET = 'e2e-inbound-neg-secret';
const INBOUND_DOMAIN = 'inbound-neg.e2e.beacon.test';

async function fillCategories(page: import('@playwright/test').Page, form: import('@playwright/test').Locator): Promise<void> {
  const categories = form.locator('select[name="notification_destination[categories][]"]');
  if ((await categories.count()) === 0) {
    return;
  }
  await categories.selectOption(['error'], { force: true }).catch(async () => {
    const ts = form.locator('.ts-control').first();
    if (await ts.isVisible().catch(() => false)) {
      await ts.click();
      await page.locator('.ts-dropdown .option').filter({ hasText: /error/i }).first().click({ force: true });
    }
  });
}

async function createDestination(
  page: import('@playwright/test').Page,
  projectUuid: string,
  opts: { label: string; type: 'slack' | 'teams'; endpointUrl: string; signingSecret: string },
): Promise<string> {
  await page.goto(`/projects/${projectUuid}/notifications/new`);
  await dismissProductTour(page);
  const form = page.getByRole('main').locator('form.notification-destination-form');
  await expect(form).toBeVisible();
  await form.locator('input[name="notification_destination[label]"]').fill(opts.label);
  await form.locator('select[name="notification_destination[type]"]').selectOption(opts.type);
  await form.locator('input[name="notification_destination[endpointUrl]"]').fill(opts.endpointUrl);
  await form.locator('input[name="notification_destination[signingSecret]"]').fill(opts.signingSecret);
  await fillCategories(page, form);
  await form.locator('button[type="submit"]').click();
  await waitForPageLoader(page);
  await expect(page).toHaveURL(new RegExp(`/projects/${projectUuid}/settings/alerts`), { timeout: 20_000 });
  const row = page.locator('#project-notification-destinations li').filter({ hasText: opts.label }).first();
  await expect(row).toBeVisible({ timeout: 15_000 });
  const edit = row.locator(`a[href*="/projects/${projectUuid}/notifications/"][href*="/edit"]`);
  const href = await edit.getAttribute('href');
  const match = href?.match(/\/notifications\/([0-9a-f-]{36})\/edit/i);
  if (!match?.[1]) {
    throw new Error(`Could not parse destination UUID from ${href ?? '(null)'}`);
  }
  return match[1];
}

async function deleteDestinationByLabel(page: import('@playwright/test').Page, projectUuid: string, label: string): Promise<void> {
  await page.goto(`/projects/${projectUuid}/settings/alerts`);
  await dismissProductTour(page);
  const row = page.locator('#project-notification-destinations li').filter({ hasText: label }).first();
  if (!(await row.isVisible().catch(() => false))) {
    return;
  }
  const del = row.locator('form[action*="/delete"] button[type="submit"]').first();
  page.once('dialog', (d) => d.accept().catch(() => undefined));
  await del.click({ force: true });
  await waitForPageLoader(page);
}

function slackSignature(body: string, secret: string, ts: string): string {
  return 'v0=' + createHmac('sha256', secret).update(`v0:${ts}:${body}`).digest('hex');
}

function slackResolveBody(destinationUuid: string, projectUuid: string, issueUuid: string, slackUserId: string): string {
  const value = JSON.stringify({
    a: 'resolve',
    d: destinationUuid,
    p: projectUuid,
    i: issueUuid,
  });
  const interaction = JSON.stringify({
    type: 'block_actions',
    user: { id: slackUserId },
    actions: [{ action_id: 'beacon_resolve', value }],
  });
  return new URLSearchParams({ payload: interaction }).toString();
}

function teamsActionToken(
  action: 'resolve' | 'assign',
  signingSecret: string,
  destinationUuid: string,
  projectUuid: string,
  issueUuid: string,
  nonce = randomBytes(16).toString('hex'),
): Record<string, string | number> {
  const claims = {
    a: action,
    d: destinationUuid,
    p: projectUuid,
    i: issueUuid,
    n: nonce,
    exp: Math.floor(Date.now() / 1000) + 86_400,
  };
  const base = `${claims.a}\n${claims.d}\n${claims.p}\n${claims.i}\n${claims.n}\n${claims.exp}`;
  return {
    ...claims,
    sig: createHmac('sha256', signingSecret).update(base).digest('hex'),
  };
}

function inboundReplyToken(issueUuid: string, recipientEmail: string, secret: string): string {
  const claims = {
    i: issueUuid,
    u: recipientEmail.trim().toLowerCase(),
    exp: Math.floor(Date.now() / 1000) + 86_400,
  };
  const payload = Buffer.from(JSON.stringify(claims))
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');
  const sig = createHmac('sha256', secret).update(payload).digest('hex');
  return `${payload}.${sig}`;
}

async function ensureAnonymousResolveOff(page: import('@playwright/test').Page): Promise<void> {
  await page.goto('/admin/ops-defaults/notifications');
  await dismissProductTour(page);
  const anon = page.locator('input[name="instance_ops_defaults[allowAnonymousResolve]"]');
  await expect(anon).toBeVisible({ timeout: 15_000 });
  if (await anon.isChecked()) {
    await anon.uncheck();
    await page.locator('form').filter({ has: anon }).locator('button[type="submit"]').click();
    await waitForPageLoader(page);
    await page.goto('/admin/ops-defaults/notifications');
    await dismissProductTour(page);
    await expect(anon).not.toBeChecked();
  }
}

async function ensureAnonymousResolveOn(page: import('@playwright/test').Page): Promise<void> {
  await page.goto('/admin/ops-defaults/notifications');
  await dismissProductTour(page);
  const anon = page.locator('input[name="instance_ops_defaults[allowAnonymousResolve]"]');
  const confirm = page.locator('input[name="instance_ops_defaults[confirmAllowAnonymousResolve]"]');
  await expect(anon).toBeVisible({ timeout: 15_000 });
  if (await anon.isChecked()) {
    return;
  }
  await anon.check();
  // Confirmation is required when enabling; field may be visually collapsed — always fill.
  await confirm.fill('ALLOW_ANONYMOUS_RESOLVE');
  await page.locator('form').filter({ has: anon }).locator('button[type="submit"]').click();
  await waitForPageLoader(page);
  await page.goto('/admin/ops-defaults/notifications');
  await dismissProductTour(page);
  await expect(anon).toBeChecked({ timeout: 15_000 });
}

async function setSlackUserId(page: import('@playwright/test').Page, slackUserId: string): Promise<void> {
  await page.goto('/account/profile');
  await dismissProductTour(page);
  const form = page.locator('[data-testid="profile-sensitive-form"]');
  await form.locator('input[name="user_profile_sensitive[slackUserId]"]').fill(slackUserId);
  await form.locator('input[name="user_profile_sensitive[currentPassword]"]').fill(DEMO_PASSWORD);
  await form.locator('button[type="submit"]').click();
  await waitForPageLoader(page);
}

test.describe('Hooks — negative paths', () => {
  test('Slack resolve without mapped Slack user → 403 (UC-HOOK-08)', async ({ page, request }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    await setSlackUserId(page, '');
    const label = `e2e-hook08-${Date.now().toString(36)}`;
    try {
      const destUuid = await createDestination(page, uuid, {
        label,
        type: 'slack',
        endpointUrl: 'https://hooks.slack.com/services/T/B/e2e-hook08',
        signingSecret: SLACK_SECRET,
      });
      const body = slackResolveBody(destUuid, uuid, issueUuid, 'U_UNMAPPED_E2E');
      const ts = String(Math.floor(Date.now() / 1000));
      const res = await request.post('/hooks/slack/interactions', {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Slack-Request-Timestamp': ts,
          'X-Slack-Signature': slackSignature(body, SLACK_SECRET, ts),
        },
        data: body,
        failOnStatusCode: false,
      });
      expect(res.status(), await res.text()).toBe(403);
    } finally {
      await deleteDestinationByLabel(page, uuid, label).catch(() => undefined);
    }
  });

  test('Teams resolve without anonymous flag → 403 (UC-HOOK-09)', async ({ page, request }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    await ensureAnonymousResolveOff(page);
    const label = `e2e-hook09-${Date.now().toString(36)}`;
    try {
      const destUuid = await createDestination(page, uuid, {
        label,
        type: 'teams',
        endpointUrl: 'https://outlook.office.com/webhook/e2e-hook09',
        signingSecret: TEAMS_SECRET,
      });
      const token = teamsActionToken('resolve', TEAMS_SECRET, destUuid, uuid, issueUuid);
      const res = await request.post('/hooks/teams/actions', {
        headers: { 'Content-Type': 'application/json' },
        data: token,
        failOnStatusCode: false,
      });
      expect(res.status(), await res.text()).toBe(403);
    } finally {
      await deleteDestinationByLabel(page, uuid, label).catch(() => undefined);
    }
  });

  test('Teams action token replay rejected (UC-HOOK-10)', async ({ page, request }) => {
    test.setTimeout(120_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const label = `e2e-hook10-${Date.now().toString(36)}`;
    let anonymousWasOn = false;
    try {
      const destUuid = await createDestination(page, uuid, {
        label,
        type: 'teams',
        endpointUrl: 'https://outlook.office.com/webhook/e2e-hook10',
        signingSecret: TEAMS_SECRET,
      });

      await page.goto('/admin/ops-defaults/notifications');
      await dismissProductTour(page);
      const anon = page.locator('input[name="instance_ops_defaults[allowAnonymousResolve]"]');
      anonymousWasOn = await anon.isChecked();
      await ensureAnonymousResolveOn(page);

      const token = teamsActionToken('resolve', TEAMS_SECRET, destUuid, uuid, issueUuid);
      const first = await request.post('/hooks/teams/actions', {
        headers: { 'Content-Type': 'application/json' },
        data: token,
        failOnStatusCode: false,
      });
      expect(first.status(), await first.text()).toBeLessThan(400);

      const second = await request.post('/hooks/teams/actions', {
        headers: { 'Content-Type': 'application/json' },
        data: token,
        failOnStatusCode: false,
      });
      expect(second.status(), await second.text()).toBe(409);
      await expect(await second.text()).toMatch(/already used/i);
    } finally {
      await deleteDestinationByLabel(page, uuid, label).catch(() => undefined);
      if (!anonymousWasOn) {
        await ensureAnonymousResolveOff(page).catch(() => undefined);
      }
    }
  });

  test('inbound duplicate Message-Id is idempotent (UC-HOOK-11)', async ({ page, request }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const marker = `e2e-hook11-${Date.now().toString(36)}`;
    let previousEnabled = false;
    let previousDomain = '';
    try {
      await page.goto('/admin/ops-defaults/inbound');
      await dismissProductTour(page);
      const enabled = page.locator('input[name="instance_ops_defaults[inboundEmailEnabled]"]');
      previousEnabled = await enabled.isChecked();
      const domain = page.locator('input[name="instance_ops_defaults[inboundMailDomain]"]');
      previousDomain = (await domain.inputValue().catch(() => '')) || '';
      if (!previousEnabled) {
        await enabled.check();
      }
      await domain.fill(INBOUND_DOMAIN);
      await page.locator('input[name="instance_ops_defaults[plainInboundWebhookSecret]"]').fill(INBOUND_SECRET);
      await page.locator('form').filter({ has: enabled }).locator('button[type="submit"]').click();
      await waitForPageLoader(page);

      const token = inboundReplyToken(issueUuid, DEMO_EMAIL, INBOUND_SECRET);
      const form = {
        sender: DEMO_EMAIL,
        recipient: `reply+${token}@${INBOUND_DOMAIN}`,
        'body-plain': `${marker} first`,
        'Message-Id': `<${marker}@example.com>`,
      };
      const headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Beacon-Inbound-Secret': INBOUND_SECRET,
      };

      const first = await request.post('/hooks/email/inbound', { headers, form, failOnStatusCode: false });
      expect(first.status(), await first.text()).toBeLessThan(400);
      await expect(await first.text()).toMatch(/created|duplicate/i);

      const second = await request.post('/hooks/email/inbound', { headers, form, failOnStatusCode: false });
      expect(second.status(), await second.text()).toBeLessThan(400);
      await expect(await second.text()).toMatch(/duplicate/i);
    } finally {
      await page.goto('/admin/ops-defaults/inbound');
      await dismissProductTour(page);
      const enabled = page.locator('input[name="instance_ops_defaults[inboundEmailEnabled]"]');
      if (await enabled.isVisible().catch(() => false)) {
        if (previousEnabled) {
          await enabled.check();
        } else {
          await enabled.uncheck();
        }
        await page.locator('input[name="instance_ops_defaults[inboundMailDomain]"]').fill(previousDomain);
        await page.locator('form').filter({ has: enabled }).locator('button[type="submit"]').click();
        await waitForPageLoader(page);
      }
    }
  });

  test('inbound From ≠ token recipient ignored (UC-HOOK-12)', async ({ page, request }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const marker = `e2e-hook12-${Date.now().toString(36)}`;
    let previousEnabled = false;
    let previousDomain = '';
    try {
      await page.goto('/admin/ops-defaults/inbound');
      await dismissProductTour(page);
      const enabled = page.locator('input[name="instance_ops_defaults[inboundEmailEnabled]"]');
      previousEnabled = await enabled.isChecked();
      const domain = page.locator('input[name="instance_ops_defaults[inboundMailDomain]"]');
      previousDomain = (await domain.inputValue().catch(() => '')) || '';
      if (!previousEnabled) {
        await enabled.check();
      }
      await domain.fill(INBOUND_DOMAIN);
      await page.locator('input[name="instance_ops_defaults[plainInboundWebhookSecret]"]').fill(INBOUND_SECRET);
      await page.locator('form').filter({ has: enabled }).locator('button[type="submit"]').click();
      await waitForPageLoader(page);

      const token = inboundReplyToken(issueUuid, DEMO_EMAIL, INBOUND_SECRET);
      const res = await request.post('/hooks/email/inbound', {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Beacon-Inbound-Secret': INBOUND_SECRET,
        },
        form: {
          sender: 'spoofed-other@example.invalid',
          recipient: `reply+${token}@${INBOUND_DOMAIN}`,
          'body-plain': `${marker} spoofed`,
          'Message-Id': `<${marker}@example.com>`,
        },
        failOnStatusCode: false,
      });
      expect(res.status(), await res.text()).toBeLessThan(400);
      await expect(await res.text()).toMatch(/ignored/i);
    } finally {
      await page.goto('/admin/ops-defaults/inbound');
      await dismissProductTour(page);
      const enabled = page.locator('input[name="instance_ops_defaults[inboundEmailEnabled]"]');
      if (await enabled.isVisible().catch(() => false)) {
        if (previousEnabled) {
          await enabled.check();
        } else {
          await enabled.uncheck();
        }
        await page.locator('input[name="instance_ops_defaults[inboundMailDomain]"]').fill(previousDomain);
        await page.locator('form').filter({ has: enabled }).locator('button[type="submit"]').click();
        await waitForPageLoader(page);
      }
    }
  });

  test('Slack stale timestamp is rejected (UC-HOOK-13)', async ({ page, request }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const label = `e2e-hook13-${Date.now().toString(36)}`;
    try {
      const destUuid = await createDestination(page, uuid, {
        label,
        type: 'slack',
        endpointUrl: 'https://hooks.slack.com/services/T/B/e2e-hook13',
        signingSecret: SLACK_SECRET,
      });
      const body = slackResolveBody(destUuid, uuid, issueUuid, 'U_STALE_E2E');
      const ts = String(Math.floor(Date.now() / 1000) - 400);
      const res = await request.post('/hooks/slack/interactions', {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Slack-Request-Timestamp': ts,
          'X-Slack-Signature': slackSignature(body, SLACK_SECRET, ts),
        },
        data: body,
        failOnStatusCode: false,
      });
      expect(res.status(), await res.text()).toBe(401);
      await expect(await res.text()).toMatch(/invalid signature/i);
    } finally {
      await deleteDestinationByLabel(page, uuid, label).catch(() => undefined);
    }
  });

  test('Slack action for a different project is forbidden (UC-HOOK-14)', async ({ page, request }) => {
    test.setTimeout(90_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const label = `e2e-hook14-${Date.now().toString(36)}`;
    try {
      const destUuid = await createDestination(page, uuid, {
        label,
        type: 'slack',
        endpointUrl: 'https://hooks.slack.com/services/T/B/e2e-hook14',
        signingSecret: SLACK_SECRET,
      });
      const otherProject = '00000000-0000-4000-8000-000000000099';
      const body = slackResolveBody(destUuid, otherProject, issueUuid, 'U_MISMATCH_E2E');
      const ts = String(Math.floor(Date.now() / 1000));
      const res = await request.post('/hooks/slack/interactions', {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Slack-Request-Timestamp': ts,
          'X-Slack-Signature': slackSignature(body, SLACK_SECRET, ts),
        },
        data: body,
        failOnStatusCode: false,
      });
      expect(res.status(), await res.text()).toBe(403);
      await expect(await res.text()).toMatch(/project mismatch/i);
    } finally {
      await deleteDestinationByLabel(page, uuid, label).catch(() => undefined);
    }
  });
});
