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
} from './helpers';

const SLACK_SECRET = 'e2e-slack-signing-secret';
const TEAMS_SECRET = 'e2e-teams-signing-secret';
const INBOUND_SECRET = 'e2e-inbound-webhook-secret';
const INBOUND_DOMAIN = 'inbound.e2e.beacon.test';
const SLACK_USER_ID = 'U_E2E_HOOKS';

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
  const secret = form.locator('input[name="notification_destination[signingSecret]"]');
  await expect(secret).toBeVisible({ timeout: 10_000 });
  await secret.fill(opts.signingSecret);
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

function teamsActionToken(
  action: 'resolve' | 'assign',
  signingSecret: string,
  destinationUuid: string,
  projectUuid: string,
  issueUuid: string,
): Record<string, string | number> {
  const claims = {
    a: action,
    d: destinationUuid,
    p: projectUuid,
    i: issueUuid,
    n: randomBytes(16).toString('hex'),
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

async function setSlackUserId(page: import('@playwright/test').Page, slackUserId: string): Promise<void> {
  await page.goto('/account/profile');
  await dismissProductTour(page);
  const form = page.locator('form').filter({ has: page.locator('input[name="user_preferences[slackUserId]"]') });
  await expect(form).toBeVisible({ timeout: 15_000 });
  await form.locator('input[name="user_preferences[slackUserId]"]').fill(slackUserId);
  await form.locator('input[name="user_preferences[currentPassword]"]').fill(DEMO_PASSWORD);
  await form.locator('button[type="submit"]').click();
  await waitForPageLoader(page);
  await expect(page).not.toHaveURL(/\/login/);
}

async function restoreIssueUnresolved(page: import('@playwright/test').Page, projectUuid: string, issueUuid: string): Promise<void> {
  await page.goto(`/projects/${projectUuid}/issues/${issueUuid}`);
  await dismissProductTour(page);
  const unresolvedForm = page.locator('form.issue-status-actions__form').filter({
    has: page.locator('input[name="status"][value="unresolved"]'),
  });
  if (await unresolvedForm.locator('button[type="submit"]').first().isVisible().catch(() => false)) {
    await unresolvedForm.locator('button[type="submit"]').first().click();
    await waitForPageLoader(page);
  }
}

test.describe('Hooks — forged happy paths', () => {
  test('Slack resolve then assign with forged signature (UC-HOOK-05)', async ({ page, request }) => {
    test.setTimeout(120_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const label = `e2e-slack-hook-${Date.now().toString(36)}`;
    await setSlackUserId(page, SLACK_USER_ID);

    let destUuid = '';
    try {
      destUuid = await createDestination(page, uuid, {
        label,
        type: 'slack',
        endpointUrl: 'https://hooks.slack.com/services/T/E2E/X',
        signingSecret: SLACK_SECRET,
      });

      // Ensure unresolved before resolve.
      await restoreIssueUnresolved(page, uuid, issueUuid);

      const value = JSON.stringify({ a: 'resolve', d: destUuid, p: uuid, i: issueUuid });
      const interaction = {
        type: 'block_actions',
        user: { id: SLACK_USER_ID },
        actions: [{ action_id: 'beacon_resolve', value }],
      };
      const body = new URLSearchParams({ payload: JSON.stringify(interaction) }).toString();
      const ts = String(Math.floor(Date.now() / 1000));
      const resolveRes = await request.post('/hooks/slack/interactions', {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Slack-Request-Timestamp': ts,
          'X-Slack-Signature': slackSignature(body, SLACK_SECRET, ts),
        },
        data: body,
        failOnStatusCode: false,
      });
      expect(resolveRes.status(), await resolveRes.text()).toBeLessThan(400);

      await page.goto(`/projects/${uuid}/issues/${issueUuid}`);
      await dismissProductTour(page);
      await expect(page.locator('.issue-badge--status-resolved')).toBeVisible({ timeout: 15_000 });

      await restoreIssueUnresolved(page, uuid, issueUuid);

      const assignValue = JSON.stringify({ a: 'assign', d: destUuid, p: uuid, i: issueUuid });
      const assignInteraction = {
        type: 'block_actions',
        user: { id: SLACK_USER_ID },
        actions: [{ action_id: 'beacon_assign', value: assignValue }],
      };
      const assignBody = new URLSearchParams({ payload: JSON.stringify(assignInteraction) }).toString();
      const assignTs = String(Math.floor(Date.now() / 1000));
      const assignRes = await request.post('/hooks/slack/interactions', {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Slack-Request-Timestamp': assignTs,
          'X-Slack-Signature': slackSignature(assignBody, SLACK_SECRET, assignTs),
        },
        data: assignBody,
        failOnStatusCode: false,
      });
      expect(assignRes.status(), await assignRes.text()).toBeLessThan(400);

      await page.goto(`/projects/${uuid}/issues/${issueUuid}`);
      await dismissProductTour(page);
      await expect(page.locator('body')).toContainText(/admin@symfony-beacon\.local|Assignee|Asignad/i);
    } finally {
      await deleteDestinationByLabel(page, uuid, label).catch(() => undefined);
      await setSlackUserId(page, '').catch(() => undefined);
      await restoreIssueUnresolved(page, uuid, issueUuid).catch(() => undefined);
    }
  });

  test('Teams assign-me with forged token while logged in (UC-HOOK-06)', async ({ page, request }) => {
    test.setTimeout(120_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const label = `e2e-teams-hook-${Date.now().toString(36)}`;
    let destUuid = '';
    let anonymousWasOn = false;

    try {
      destUuid = await createDestination(page, uuid, {
        label,
        type: 'teams',
        endpointUrl: 'https://outlook.office.com/webhook/e2e-teams',
        signingSecret: TEAMS_SECRET,
      });

      const assignToken = teamsActionToken('assign', TEAMS_SECRET, destUuid, uuid, issueUuid);
      const qs = new URLSearchParams(
        Object.fromEntries(Object.entries(assignToken).map(([k, v]) => [k, String(v)])),
      ).toString();
      await page.goto(`/hooks/teams/assign-me?${qs}`);
      await page.waitForURL(new RegExp(`/projects/${uuid}/issues/${issueUuid}`), { timeout: 30_000 });
      await dismissProductTour(page);
      await expect(page.locator('body')).toContainText(/admin@symfony-beacon\.local|Assignee|Asignad/i);

      // Resolve via anonymous toggle (restored in finally).
      await page.goto('/admin/ops-defaults/notifications');
      await dismissProductTour(page);
      const anon = page.locator('input[name="instance_ops_defaults[allowAnonymousResolve]"]');
      await expect(anon).toBeVisible({ timeout: 15_000 });
      anonymousWasOn = await anon.isChecked();
      if (!anonymousWasOn) {
        await anon.check();
        const confirm = page.locator('input[name="instance_ops_defaults[confirmAllowAnonymousResolve]"]');
        if (await confirm.isVisible().catch(() => false)) {
          await confirm.fill('ALLOW_ANONYMOUS_RESOLVE');
        }
        await page.locator('form').filter({ has: anon }).locator('button[type="submit"]').click();
        await waitForPageLoader(page);
      }

      await restoreIssueUnresolved(page, uuid, issueUuid);
      const resolveToken = teamsActionToken('resolve', TEAMS_SECRET, destUuid, uuid, issueUuid);
      const resolveRes = await request.post('/hooks/teams/actions', {
        headers: { 'Content-Type': 'application/json' },
        data: resolveToken,
        failOnStatusCode: false,
      });
      expect(resolveRes.status(), await resolveRes.text()).toBeLessThan(400);

      await page.goto(`/projects/${uuid}/issues/${issueUuid}`);
      await dismissProductTour(page);
      await expect(page.locator('.issue-badge--status-resolved')).toBeVisible({ timeout: 15_000 });
    } finally {
      await deleteDestinationByLabel(page, uuid, label).catch(() => undefined);
      await restoreIssueUnresolved(page, uuid, issueUuid).catch(() => undefined);
      if (!anonymousWasOn) {
        await page.goto('/admin/ops-defaults/notifications');
        await dismissProductTour(page);
        const anon = page.locator('input[name="instance_ops_defaults[allowAnonymousResolve]"]');
        if (await anon.isVisible().catch(() => false) && (await anon.isChecked().catch(() => false))) {
          await anon.uncheck();
          await page.locator('form').filter({ has: anon }).locator('button[type="submit"]').click();
          await waitForPageLoader(page);
        }
      }
    }
  });

  test('inbound email reply creates issue comment (UC-HOOK-07)', async ({ page, request }) => {
    test.setTimeout(120_000);
    const uuid = await resolveDemoProjectUuid(page);
    const issueUuid = await openFirstIssue(page, uuid);
    if (!issueUuid) {
      requireSampleOrSkip(false, 'No issues — run make seed-sample');
      return;
    }

    const marker = `e2e-inbound-${Date.now().toString(36)}`;
    let previousEnabled = false;
    let previousDomain = '';

    try {
      await page.goto('/admin/ops-defaults/inbound');
      await dismissProductTour(page);
      const enabled = page.locator('input[name="instance_ops_defaults[inboundEmailEnabled]"]');
      await expect(enabled).toBeVisible({ timeout: 15_000 });
      previousEnabled = await enabled.isChecked();
      const domain = page.locator('input[name="instance_ops_defaults[inboundMailDomain]"]');
      previousDomain = (await domain.inputValue().catch(() => '')) || '';
      if (!(await enabled.isChecked())) {
        await enabled.check();
      }
      await domain.fill(INBOUND_DOMAIN);
      const secret = page.locator('input[name="instance_ops_defaults[plainInboundWebhookSecret]"]');
      await secret.fill(INBOUND_SECRET);
      await page.locator('form').filter({ has: enabled }).locator('button[type="submit"]').click();
      await waitForPageLoader(page);

      const token = inboundReplyToken(issueUuid, DEMO_EMAIL, INBOUND_SECRET);
      const recipient = `reply+${token}@${INBOUND_DOMAIN}`;
      const res = await request.post('/hooks/email/inbound', {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Beacon-Inbound-Secret': INBOUND_SECRET,
        },
        form: {
          sender: DEMO_EMAIL,
          recipient,
          'body-plain': `${marker} looks good from Playwright.\n\nOn Mon someone wrote:\n> old`,
          'Message-Id': `<${marker}@example.com>`,
        },
        failOnStatusCode: false,
      });
      expect(res.status(), await res.text()).toBeLessThan(400);
      await expect(await res.text()).toMatch(/created|duplicate/i);

      await page.goto(`/projects/${uuid}/issues/${issueUuid}`);
      await dismissProductTour(page);
      await expect(page.locator('body')).toContainText(marker, { timeout: 15_000 });
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
        const domain = page.locator('input[name="instance_ops_defaults[inboundMailDomain]"]');
        await domain.fill(previousDomain);
        await page.locator('form').filter({ has: enabled }).locator('button[type="submit"]').click();
        await waitForPageLoader(page);
      }
    }
  });
});
