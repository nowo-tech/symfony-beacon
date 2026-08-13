import { test, expect } from '@playwright/test';

/**
 * Interactive hooks / inbound email — reject bad payloads without 5xx.
 * Happy-path signing is Gap (needs Slack/Teams secrets + inbound token).
 */
test.describe('Hooks — use cases', () => {
  test('Slack interactions reject empty/unsigned body (UC-HOOK-01)', async ({ request }) => {
    const res = await request.post('/hooks/slack/interactions', {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      data: 'payload={}',
      failOnStatusCode: false,
    });
    expect(res.status()).toBeGreaterThanOrEqual(400);
    expect(res.status()).toBeLessThan(500);
  });

  test('Teams actions reject empty JSON (UC-HOOK-02)', async ({ request }) => {
    const res = await request.post('/hooks/teams/actions', {
      headers: { 'Content-Type': 'application/json' },
      data: {},
      failOnStatusCode: false,
    });
    expect(res.status()).toBeGreaterThanOrEqual(400);
    expect(res.status()).toBeLessThan(500);
  });

  test('Teams assign-me rejects empty JSON (UC-HOOK-03)', async ({ request }) => {
    const res = await request.post('/hooks/teams/assign-me', {
      headers: { 'Content-Type': 'application/json' },
      data: {},
      failOnStatusCode: false,
    });
    expect(res.status()).toBeGreaterThanOrEqual(400);
    expect(res.status()).toBeLessThan(500);
  });

  test('inbound email rejects unauthenticated request (UC-HOOK-04)', async ({ request }) => {
    const res = await request.post('/hooks/email/inbound', {
      headers: { 'Content-Type': 'application/json' },
      data: { text: 'reply body' },
      failOnStatusCode: false,
    });
    expect(res.status()).toBeGreaterThanOrEqual(400);
    expect(res.status()).toBeLessThan(500);
  });
});
