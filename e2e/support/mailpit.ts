import { expect, test } from '@playwright/test';

/** Mailpit HTTP API (host-published UI port). Playwright runs with `--network=host`. */
export function mailpitBaseUrl(): string {
  return (process.env.PLAYWRIGHT_MAILPIT_URL ?? 'http://127.0.0.1:18026').replace(/\/$/, '');
}

export async function mailpitIsReachable(): Promise<boolean> {
  try {
    const res = await fetch(`${mailpitBaseUrl()}/api/v1/info`, { signal: AbortSignal.timeout(3_000) });
    return res.ok;
  } catch {
    return false;
  }
}

/** CI / PLAYWRIGHT_REQUIRE_MAILPIT=1: missing Mailpit fails instead of skip. */
export function requireMailpitOrSkip(ready: boolean, reason: string): void {
  if (ready) {
    return;
  }
  if (process.env.CI || process.env.PLAYWRIGHT_REQUIRE_MAILPIT === '1' || process.env.PLAYWRIGHT_REQUIRE_SAMPLE === '1') {
    throw new Error(reason);
  }
  test.skip(true, reason);
}

export async function mailpitDeleteAll(): Promise<void> {
  const res = await fetch(`${mailpitBaseUrl()}/api/v1/messages`, { method: 'DELETE' });
  expect(res.ok, `Mailpit DELETE messages → ${res.status}`).toBeTruthy();
}

type MailpitMessageSummary = {
  ID: string;
  Subject: string;
  To?: Array<{ Address?: string }>;
  Created: string;
};

type MailpitMessage = MailpitMessageSummary & {
  Text?: string;
  HTML?: string;
};

/**
 * Poll Mailpit until a message matches, then return absolute URL extracted from body.
 */
export async function mailpitWaitForLink(options: {
  toAddress: string;
  subjectIncludes?: RegExp;
  linkPattern: RegExp;
  timeoutMs?: number;
}): Promise<string> {
  const timeoutMs = options.timeoutMs ?? 30_000;
  const deadline = Date.now() + timeoutMs;
  const to = options.toAddress.toLowerCase();

  while (Date.now() < deadline) {
    const listRes = await fetch(`${mailpitBaseUrl()}/api/v1/messages?limit=50`);
    expect(listRes.ok).toBeTruthy();
    const list = (await listRes.json()) as { messages?: MailpitMessageSummary[] };
    const candidates = (list.messages ?? []).filter((m) => {
      const recipients = (m.To ?? []).map((t) => (t.Address ?? '').toLowerCase());
      if (!recipients.includes(to)) {
        return false;
      }
      if (options.subjectIncludes && !options.subjectIncludes.test(m.Subject ?? '')) {
        return false;
      }
      return true;
    });

    for (const summary of candidates) {
      const msgRes = await fetch(`${mailpitBaseUrl()}/api/v1/message/${summary.ID}`);
      if (!msgRes.ok) {
        continue;
      }
      const msg = (await msgRes.json()) as MailpitMessage;
      const body = `${msg.Text ?? ''}\n${msg.HTML ?? ''}`;
      const match = body.match(options.linkPattern);
      if (match?.[0]) {
        let href = match[0];
        // Prefer href="..." capture group when pattern uses one.
        if (match[1]) {
          href = match[1];
        }
        href = href.replace(/&amp;/g, '&').trim();
        if (href.startsWith('http')) {
          return href.replace(/^https?:\/\/[^/]+/i, '');
        }
        return href.startsWith('/') ? href : `/${href}`;
      }
    }

    await new Promise((r) => setTimeout(r, 500));
  }

  throw new Error(
    `Mailpit: no message to ${options.toAddress} matching ${options.subjectIncludes ?? '/./'} with link ${options.linkPattern} within ${timeoutMs}ms`,
  );
}
